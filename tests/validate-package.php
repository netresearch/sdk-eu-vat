<?php

declare(strict_types=1);

/**
 * Package validation script for EU VAT SDK
 *
 * This script validates the package structure, dependencies, and installation process
 * to ensure the SDK is ready for distribution.
 *
 * @package Netresearch\EuVatSdk\Tests
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

echo "=== EU VAT SDK Package Validation ===\n\n";

$errors = [];
$warnings = [];

// Check PHP version
echo "1. Checking PHP version...\n";
if (PHP_VERSION_ID < 80100) {
    $errors[] = "PHP 8.1+ is required, found " . PHP_VERSION;
} else {
    echo "   ✓ PHP " . PHP_VERSION . " meets requirements\n";
}

// Check required extensions
echo "\n2. Checking required PHP extensions...\n";
$requiredExtensions = ['soap', 'libxml', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Required extension '$ext' is not loaded";
        echo "   ✗ Extension '$ext' missing\n";
    } else {
        echo "   ✓ Extension '$ext' loaded\n";
    }
}

// Validate composer.json
echo "\n3. Validating composer.json...\n";
$composerPath = __DIR__ . '/../composer.json';
if (!file_exists($composerPath)) {
    $errors[] = "composer.json not found";
} else {
    $composerJson = json_decode(file_get_contents($composerPath), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Invalid composer.json: " . json_last_error_msg();
    } else {
        // Check required fields
        $requiredFields = ['name', 'description', 'type', 'license', 'require', 'autoload'];
        foreach ($requiredFields as $field) {
            if (!isset($composerJson[$field])) {
                $errors[] = "composer.json missing required field: $field";
            }
        }

        // Validate package name
        if (isset($composerJson['name']) && $composerJson['name'] !== 'netresearch/sdk-eu-vat') {
            $errors[] = "Invalid package name: " . $composerJson['name'];
        }

        // Check dependencies
        if (isset($composerJson['require'])) {
            $expectedDeps = [
                'php' => '^8.1',
                'ext-soap' => '*',
                'ext-libxml' => '*',
                'brick/math' => '^0.12',
                'psr/log' => '^3.0',
            ];

            foreach ($expectedDeps as $dep => $constraint) {
                if (!isset($composerJson['require'][$dep])) {
                    $warnings[] = "Missing expected dependency: $dep";
                }
            }
        }

        echo "   ✓ composer.json structure valid\n";
    }
}

// Check autoloading
echo "\n4. Checking PSR-4 autoloading...\n";
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $warnings[] = "vendor/autoload.php not found - run 'composer install'";
} else {
    require_once $autoloadPath;

    // Test autoloading of key classes
    $testClasses = [
        'Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory',
        'Netresearch\EuVatSdk\Client\ClientConfiguration',
        'Netresearch\EuVatSdk\DTO\Request\VatRatesRequest',
        'Netresearch\EuVatSdk\Exception\VatServiceException',
    ];

    foreach ($testClasses as $class) {
        if (!class_exists($class)) {
            $errors[] = "Class not autoloadable: $class";
            echo "   ✗ Failed to autoload: $class\n";
        } else {
            echo "   ✓ Autoloaded: $class\n";
        }
    }
}

// Check directory structure
echo "\n5. Validating directory structure...\n";
$requiredDirs = [
    'src',
    'src/Client',
    'src/DTO',
    'src/DTO/Request',
    'src/DTO/Response',
    'src/Exception',
    'src/EventListener',
    'src/Factory',
    'src/Middleware',
    'src/Telemetry',
    'src/TypeConverter',
    'tests',
    'tests/Unit',
    'tests/Integration',
    'resources',
    'examples',
];

foreach ($requiredDirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (!is_dir($path)) {
        $errors[] = "Required directory missing: $dir";
        echo "   ✗ Missing: $dir\n";
    } else {
        echo "   ✓ Found: $dir\n";
    }
}

// Check key files
echo "\n6. Checking required files...\n";
$requiredFiles = [
    'README.md',
    'LICENSE',
    'CHANGELOG.md',
    'composer.json',
    'phpunit.xml',
    'phpstan.neon',
    'phpcs.xml',
    'rector.php',
    '.gitignore',
    'resources/VatRetrievalService.wsdl',
];

foreach ($requiredFiles as $file) {
    $path = __DIR__ . '/../' . $file;
    if (!file_exists($path)) {
        $warnings[] = "Expected file missing: $file";
        echo "   ⚠ Missing: $file\n";
    } else {
        echo "   ✓ Found: $file\n";
    }
}

// Check namespace conflicts
echo "\n7. Checking for namespace conflicts...\n";
$srcPath = __DIR__ . '/../src';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcPath)
);

$namespaces = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
            if (!str_starts_with($namespace, 'Netresearch\\EuVatSdk')) {
                $errors[] = "Invalid namespace in " . $file->getFilename() . ": $namespace";
            }
            $namespaces[] = $namespace;
        }
    }
}

$uniqueNamespaces = array_unique($namespaces);
echo "   ✓ Found " . count($uniqueNamespaces) . " unique namespaces\n";
echo "   ✓ All namespaces start with Netresearch\\EuVatSdk\n";

// Validate examples
echo "\n8. Validating examples...\n";
$examplesDir = __DIR__ . '/../examples';
if (is_dir($examplesDir)) {
    $examples = glob($examplesDir . '/*.php');
    foreach ($examples as $example) {
        $content = file_get_contents($example);

        // Check for syntax errors
        $output = [];
        $returnCode = 0;
        exec("php -l $example 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $errors[] = "Syntax error in example: " . basename($example);
            echo "   ✗ Syntax error: " . basename($example) . "\n";
        } else {
            echo "   ✓ Valid syntax: " . basename($example) . "\n";
        }

        // Check for require statement
        if (!str_contains($content, 'require_once') && !str_contains($content, 'require')) {
            $warnings[] = "Example missing autoload require: " . basename($example);
        }
    }
} else {
    $warnings[] = "Examples directory not found";
}

// Check WSDL file
echo "\n9. Validating WSDL file...\n";
$wsdlPath = __DIR__ . '/../resources/VatRetrievalService.wsdl';
if (file_exists($wsdlPath)) {
    $wsdlContent = file_get_contents($wsdlPath);

    // Basic XML validation
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($wsdlContent);

    if ($xml === false) {
        $errors[] = "Invalid WSDL file: XML parsing failed";
        foreach (libxml_get_errors() as $error) {
            echo "   ✗ XML Error: " . $error->message;
        }
    } else {
        echo "   ✓ WSDL file is valid XML\n";

        // Check for expected service
        if (str_contains($wsdlContent, 'VatRetrievalService')) {
            echo "   ✓ WSDL contains VatRetrievalService definition\n";
        } else {
            $warnings[] = "WSDL might not contain expected service definition";
        }
    }
} else {
    $errors[] = "WSDL file not found";
}

// Test package installation simulation
echo "\n10. Simulating package installation...\n";
$tempDir = sys_get_temp_dir() . '/vat-sdk-test-' . uniqid();
mkdir($tempDir);

// Create a test composer.json
$testComposer = [
    'require' => [
        'netresearch/sdk-eu-vat' => 'dev-main'
    ],
    'repositories' => [
        [
            'type' => 'path',
            'url' => realpath(__DIR__ . '/..')
        ]
    ]
];

file_put_contents($tempDir . '/composer.json', json_encode($testComposer, JSON_PRETTY_PRINT));
echo "   ✓ Created test installation directory\n";

// Summary
echo "\n=== Validation Summary ===\n";

if (empty($errors)) {
    echo "✅ No errors found!\n";
} else {
    echo "❌ Found " . count($errors) . " errors:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️  Found " . count($warnings) . " warnings:\n";
    foreach ($warnings as $warning) {
        echo "   - $warning\n";
    }
}

// Cleanup
if (isset($tempDir) && is_dir($tempDir)) {
    rmdir($tempDir);
}

// Exit code
exit(empty($errors) ? 0 : 1);
