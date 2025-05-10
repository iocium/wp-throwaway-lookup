#!/usr/bin/env bash
# install-wp-tests.sh: Download and configure the WP test suite

set -e

DB_NAME=${1:-wordpress_test}
DB_USER=${2:-root}
DB_PASS=${3:-root}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress/}

download() {
  if [ "$(which curl)" ]; then
    curl -s "$1" > "$2"
  elif [ "$(which wget)" ]; then
    wget -q -O "$2" "$1"
  fi
}

install_wp() {
  if [ ! -d "$WP_CORE_DIR" ]; then
    mkdir -p "$WP_CORE_DIR"
    download https://wordpress.org/${WP_VERSION}.tar.gz /tmp/wordpress.tar.gz
    tar -xzf /tmp/wordpress.tar.gz -C /tmp
    mv /tmp/wordpress/* "$WP_CORE_DIR"
  fi
}

install_test_suite() {
  if [ ! -d "$WP_TESTS_DIR" ]; then
    mkdir -p "$WP_TESTS_DIR"
    svn co --quiet https://develop.svn.wordpress.org/tags/$(wp core version --path=$WP_CORE_DIR)/tests/phpunit/includes/ "$WP_TESTS_DIR/includes"
    svn co --quiet https://develop.svn.wordpress.org/tags/$(wp core version --path=$WP_CORE_DIR)/tests/phpunit/data/ "$WP_TESTS_DIR/data"
  fi

  cp "$WP_TESTS_DIR/includes/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"

  if [[ "$(uname)" == "Darwin" ]]; then
    sed -i '' "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i '' "s/yourdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i '' "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i '' "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i '' "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config.php"
  else
    sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config.php"
  fi
}

install_wp
install_test_suite