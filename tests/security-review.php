<?php

declare(strict_types=1);

use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Psr\Log\AbstractLogger;

/**
 * Security review script for EU VAT SDK
 *
 * This script performs security checks including input validation,
 * error message analysis, dependency scanning, and SOAP injection tests.
 *
 * @package Netresearch\EuVatSdk\Tests
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;

echo "=== EU VAT SDK Security Review ===\n\n";

$securityIssues = [];
$securityWarnings = [];
$passedChecks = [];

// 1. Input Validation Tests
echo "1. Input Validation Security\n";

$client = VatRetrievalClientFactory::create();

// Test SQL injection attempts in country codes
$sqlInjectionTests = [
    ["'; DROP TABLE users; --", "DE"],
    ["DE' OR '1'='1", "FR"],
    ["DE UNION SELECT * FROM passwords", "IT"],
];

foreach ($sqlInjectionTests as $test) {
    try {
        $request = new VatRatesRequest($test, new \DateTime('2024-01-01'));
        // If we get here, check if the values were sanitized
        $reflection = new ReflectionObject($request);
        $prop = $reflection->getProperty('memberStates');
        $prop->setAccessible(true);
        $values = $prop->getValue($request);

        // Check if dangerous input was properly handled
        foreach ($values as $value) {
            if (str_contains((string) $value, ';') || str_contains((string) $value, 'DROP')) {
                $securityIssues[] = "SQL injection payload not sanitized: $value";
            }
        }
    } catch (\Exception) {
        // Good - input was rejected
        $passedChecks[] = "SQL injection attempt blocked: " . implode(', ', $test);
    }
}

// Test XSS attempts
$xssTests = [
    ["<script>alert('XSS')</script>", "DE"],
    ["DE\"><script>alert(1)</script>", "FR"],
    ["javascript:alert('XSS')", "IT"],
];

foreach ($xssTests as $test) {
    try {
        $request = new VatRatesRequest($test, new \DateTime('2024-01-01'));
        $securityWarnings[] = "XSS payload accepted in input: " . implode(', ', $test);
    } catch (\Exception) {
        $passedChecks[] = "XSS attempt blocked: " . implode(', ', $test);
    }
}

// Test XML/SOAP injection
$xmlInjectionTests = [
    ["DE</memberState><malicious>injected</malicious><memberState>FR"],
    ["DE]]><!ENTITY xxe SYSTEM \"file:///etc/passwd\">"],
];

foreach ($xmlInjectionTests as $test) {
    try {
        $request = new VatRatesRequest($test, new \DateTime('2024-01-01'));
        $securityIssues[] = "XML injection payload accepted: " . implode(', ', $test);
    } catch (\Exception) {
        $passedChecks[] = "XML injection attempt blocked: " . implode(', ', $test);
    }
}

echo "   ✓ Tested " . count($passedChecks) . " injection attempts\n";
if ($securityIssues !== []) {
    echo "   ✗ Found " . count($securityIssues) . " input validation issues\n";
}

// 2. Error Message Information Disclosure
echo "\n2. Error Message Information Disclosure\n";

// Test various error scenarios
$errorScenarios = [
    ['scenario' => 'Invalid country', 'countries' => ['XX']],
    ['scenario' => 'Empty array', 'countries' => []],
    ['scenario' => 'Invalid date', 'date' => 'invalid-date'],
];

foreach ($errorScenarios as $scenario) {
    try {
        if (isset($scenario['date'])) {
            // This will throw before creating request
            /** @phpstan-ignore-next-line */
            new VatRatesRequest(['DE'], new \DateTime($scenario['date']));
        } else {
            $request = new VatRatesRequest(
                $scenario['countries'] ?? ['DE'],
                new \DateTime('2024-01-01')
            );
            $client->retrieveVatRates($request);
        }
    } catch (\Exception $e) {
        $errorMsg = $e->getMessage();

        // Check for sensitive information in error messages
        $sensitivePaths = [
            '/home/', '/var/', '/usr/', 'C:\\', 'D:\\',
            '.php', '.xml', '.wsdl', 'localhost',
            '127.0.0.1', 'database', 'password'
        ];

        foreach ($sensitivePaths as $sensitive) {
            if (stripos($errorMsg, $sensitive) !== false) {
                $securityWarnings[] = "Error message may contain sensitive info: " .
                    substr($errorMsg, 0, 100) . "...";
                break;
            }
        }

        // Check if error provides too much detail
        if (strlen($errorMsg) > 200) {
            $securityWarnings[] = "Error message might be too detailed (" .
                strlen($errorMsg) . " chars)";
        }
    }
}

echo "   ✓ Analyzed error messages for information disclosure\n";

// 3. Dependency Security Scan
echo "\n3. Dependency Security Scan\n";

// Check composer.lock for known vulnerabilities
$composerLock = __DIR__ . '/../composer.lock';
if (file_exists($composerLock)) {
    // Run composer audit if available
    $output = [];
    $returnCode = 0;
    exec('cd ' . __DIR__ . '/.. && composer audit 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        echo "   ✓ No known vulnerabilities in dependencies\n";
        $passedChecks[] = "Dependency audit passed";
    } else {
        $auditOutput = implode("\n", $output);
        if (str_contains($auditOutput, 'vulnerabilit')) {
            $securityIssues[] = "Composer audit found vulnerabilities";
            echo "   ✗ Vulnerabilities found in dependencies\n";
        }
    }
} else {
    $securityWarnings[] = "composer.lock not found - cannot audit dependencies";
}

// 4. Configuration Security
echo "\n4. Configuration Security\n";

// Test insecure configurations
try {
    // Test with SSL verification disabled (should warn)
    $insecureConfig = ClientConfiguration::production()
        ->withSoapOptions([
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ])
        ]);

    $securityWarnings[] = "SDK allows disabling SSL verification";
} catch (\Exception) {
    $passedChecks[] = "SDK prevents disabling SSL verification";
}

// Check default timeouts
$defaultConfig = ClientConfiguration::production();
$reflection = new ReflectionObject($defaultConfig);
$timeoutProp = $reflection->getProperty('timeout');
$timeoutProp->setAccessible(true);
$timeout = $timeoutProp->getValue($defaultConfig);

if ($timeout > 300) {
    $securityWarnings[] = "Default timeout might be too high: {$timeout}s";
} else {
    $passedChecks[] = "Default timeout is reasonable: {$timeout}s";
}

// 5. WSDL Security
echo "\n5. WSDL Security\n";

$wsdlPath = __DIR__ . '/../resources/VatRetrievalService.wsdl';
if (file_exists($wsdlPath)) {
    $wsdlContent = file_get_contents($wsdlPath);

    if ($wsdlContent !== false) {
        // Check for external entity references
        if (str_contains($wsdlContent, '<!ENTITY')) {
            $securityIssues[] = "WSDL contains entity definitions (XXE risk)";
        } else {
            $passedChecks[] = "WSDL does not contain entity definitions";
        }

        // Check for external imports
        if (preg_match('/<import.*location="http/i', $wsdlContent)) {
            $securityWarnings[] = "WSDL imports external resources over HTTP";
        } else {
            $passedChecks[] = "WSDL does not import external HTTP resources";
        }

        // Check endpoint security
        if (str_contains($wsdlContent, 'http://') && !str_contains($wsdlContent, 'https://')) {
            $securityIssues[] = "WSDL uses non-HTTPS endpoints";
        } else {
            $passedChecks[] = "WSDL uses HTTPS endpoints";
        }
    }
}

// 6. Logging Security
echo "\n6. Logging Security\n";

// Create a test logger that captures output
$logOutput = '';
$testLogger = new class extends AbstractLogger {
    /** @var array<string> */
    private array $messages = [];

    public function log($level, $message, array $context = []): void
    {
        $this->messages[] = "[{$level}] {$message} " . json_encode($context);
    }

    public function getOutput(): string
    {
        return implode("\n", $this->messages);
    }
};

// Test if sensitive data is logged
$config = ClientConfiguration::production($testLogger)
    ->withDebug(true);

$testClient = VatRetrievalClientFactory::create($config);

try {
    // Make a request with potentially sensitive data
    $request = new VatRatesRequest(['DE'], new \DateTime('2024-01-01'));
    $testClient->retrieveVatRates($request);
} catch (\Exception) {
    // Ignore errors - we're checking logging
}

// Check log output for sensitive patterns
$logOutput = $testLogger->getOutput();
$sensitivePatterns = [
    '/password/i',
    '/secret/i',
    '/token/i',
    '/key/i',
    '/auth/i',
];

foreach ($sensitivePatterns as $pattern) {
    if (preg_match($pattern, $logOutput)) {
        $securityWarnings[] = "Logs might contain sensitive data matching: $pattern";
    }
}

if ($securityWarnings === []) {
    $passedChecks[] = "Logging does not expose sensitive data";
}

// 7. Type Safety and Strict Types
echo "\n7. Type Safety Analysis\n";

$srcDir = __DIR__ . '/../src';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir)
);

$filesChecked = 0;
$strictTypeFiles = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filesChecked++;
        $content = file_get_contents($file->getPathname());

        if ($content !== false) {
            if (str_contains($content, 'declare(strict_types=1);')) {
                $strictTypeFiles++;
            } else {
                $securityWarnings[] = "File without strict types: " . $file->getFilename();
            }
        }
    }
}

if ($strictTypeFiles === $filesChecked) {
    $passedChecks[] = "All PHP files use strict types";
} else {
    echo "   ⚠ {$strictTypeFiles}/{$filesChecked} files use strict types\n";
}

// Summary
echo "\n=== Security Review Summary ===\n";

echo "\n✅ Passed Checks (" . count($passedChecks) . "):\n";
foreach (array_slice($passedChecks, 0, 5) as $check) {
    echo "   - $check\n";
}
if (count($passedChecks) > 5) {
    echo "   ... and " . (count($passedChecks) - 5) . " more\n";
}

if ($securityIssues !== []) {
    echo "\n❌ Security Issues (" . count($securityIssues) . "):\n";
    foreach ($securityIssues as $issue) {
        echo "   - $issue\n";
    }
}

if ($securityWarnings !== []) {
    echo "\n⚠️  Security Warnings (" . count($securityWarnings) . "):\n";
    foreach (array_slice($securityWarnings, 0, 5) as $warning) {
        echo "   - $warning\n";
    }
    if (count($securityWarnings) > 5) {
        echo "   ... and " . (count($securityWarnings) - 5) . " more\n";
    }
}

// Recommendations
echo "\n=== Security Recommendations ===\n";
echo "1. Ensure all input is validated against a whitelist\n";
echo "2. Keep error messages generic in production\n";
echo "3. Regularly update dependencies with 'composer update'\n";
echo "4. Use HTTPS endpoints exclusively\n";
echo "5. Enable debug logging only in development\n";
echo "6. Consider implementing rate limiting\n";
echo "7. Add security headers to API responses\n";

// Exit code
$hasIssues = $securityIssues !== [];
exit($hasIssues ? 1 : 0);
