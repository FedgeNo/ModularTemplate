#!/bin/sh
cd "$(dirname "$0")/.." || exit 1
PROJECT_ROOT="$(pwd)"

if ! command -v node >/dev/null 2>&1; then
    echo "Node.js is not installed. Install it with one of these commands:"
    case "$(uname -s)" in
        Linux)
            if [ -f /etc/debian_version ]; then
                echo "  sudo apt update && sudo apt install -y nodejs npm"
            elif [ -f /etc/redhat-release ]; then
                echo "  sudo dnf install -y nodejs npm"
            elif [ -f /etc/arch-release ]; then
                echo "  sudo pacman -S nodejs npm"
            else
                echo "  See https://nodejs.org for installation instructions."
            fi
            ;;
        Darwin)
            echo "  brew install node"
            ;;
        *)
            echo "  Download from https://nodejs.org"
            ;;
    esac
    exit 1
fi

if [ ! -f "$PROJECT_ROOT/package.json" ]; then
    echo "No package.json found. Run this from the project root to create one:"
    echo "  cd $PROJECT_ROOT && npm init -y"
    echo "Then run the test setup again."
    exit 1
fi

if [ ! -d "$PROJECT_ROOT/node_modules/jsdom" ]; then
    echo "Test dependency 'jsdom' is not installed. Run this from the project root:"
    echo "  cd $PROJECT_ROOT && npm install"
    echo "Then try again."
    exit 1
fi

node "$PROJECT_ROOT/bin/run-js-tests.js"
