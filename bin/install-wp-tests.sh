#!/usr/bin/env bash
# Install WordPress test suite for integration tests.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# Example:
#   bin/install-wp-tests.sh abilities_test root '' localhost latest
#
# Sets up:
#   /tmp/wordpress/           — WordPress installation
#   /tmp/wordpress-tests-lib/ — WordPress test suite (WP_TESTS_DIR)
#
# After running, set WP_TESTS_DIR=/tmp/wordpress-tests-lib and run PHPUnit.

set -ex

DB_NAME=${1:-abilities_test}
DB_USER=${2:-root}
DB_PASS=${3:-''}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress}

download() {
    if command -v curl >/dev/null 2>&1; then
        curl -s "$1" > "$2"
    elif command -v wget >/dev/null 2>&1; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
    LATEST_VERSION=$(download https://api.wordpress.org/core/version-check/1.7/ - | grep -o '"version":"[^"]*"' | head -1 | sed 's/"version":"//;s/"//')
    if [[ $LATEST_VERSION == "$WP_VERSION."* ]]; then
        WP_VERSION=$LATEST_VERSION
    fi
fi

if [[ $WP_VERSION == 'latest' ]]; then
    WP_VERSION=$(download https://api.wordpress.org/core/version-check/1.7/ - | grep -o '"version":"[^"]*"' | head -1 | sed 's/"version":"//;s/"//')
fi

WP_TESTS_TAG="tags/$WP_VERSION"
if [[ $WP_VERSION == 'trunk' ]]; then
    WP_TESTS_TAG='trunk'
fi

# Install WordPress.
if [ ! -d "$WP_CORE_DIR/src" ]; then
    mkdir -p "$WP_CORE_DIR"
    download "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
fi

if [ ! -d "$WP_TESTS_DIR/includes" ]; then
    mkdir -p "$WP_TESTS_DIR"
    svn co --quiet --ignore-externals \
        "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" \
        "$WP_TESTS_DIR/includes"
    svn co --quiet --ignore-externals \
        "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" \
        "$WP_TESTS_DIR/data"
fi

if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
    download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" \
        "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|dirname( __FILE__ ) . '/src/'|'$WP_CORE_DIR/'|" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|youremptytestdbnamehere|$DB_NAME|" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|yourusernamehere|$DB_USER|" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|yourpasswordhere|$DB_PASS|" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config.php"
fi

# Create test database.
mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" \
    --host="$DB_HOST" --protocol=tcp 2>/dev/null || true
