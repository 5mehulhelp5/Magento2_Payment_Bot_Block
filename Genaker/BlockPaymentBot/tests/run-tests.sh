#!/bin/bash
# Standalone test runner for BlockPaymentBot tests

# Get the directory where this script is located
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd "$DIR"

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install
    echo ""
fi

# Run Pest tests
echo "Running Pest tests..."
./vendor/bin/pest "$@"


