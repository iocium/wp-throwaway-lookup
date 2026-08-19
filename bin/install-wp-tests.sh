#!/usr/bin/env bash
# install-wp-tests.sh: Download and configure the WP test suite

set -e

DB_NAME=${1:-wordpress_test}
DB_USER=${2:-root}
DB_PASS=${3:-root}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

download() {
  if [ "$(which curl)" ]; then
    curl -fsL "$1" > "$2"
  elif [ "$(which wget)" ]; then
    wget -q -O "$2" "$1"
  else
    echo "Error: curl or wget is required to download files." >&2
    exit 1
  fi
}

install_wp() {
  if [ -d "$WP_CORE_DIR" ] && [ -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
    return
  fi

  mkdir -p "$WP_CORE_DIR"

  case "$WP_VERSION" in
    latest)
      local archive_url="https://wordpress.org/latest.tar.gz"
      ;;
    nightly)
      local archive_url="https://wordpress.org/nightly-builds/wordpress-latest.zip"
      ;;
    *)
      local archive_url="https://wordpress.org/wordpress-$WP_VERSION.tar.gz"
      ;;
  esac

  echo "Downloading WordPress from $archive_url ..."
  download "$archive_url" /tmp/wordpress.tar.gz

  if [ ! -s /tmp/wordpress.tar.gz ]; then
    echo "Error: WordPress archive download failed." >&2
    exit 1
  fi

  # The WordPress tarball contains a top-level `wordpress/` directory.
  # --strip-components=1 extracts the files directly into WP_CORE_DIR.
  tar --strip-components=1 -xzf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
}

install_test_suite() {
  if [ -d "$WP_TESTS_DIR" ] && [ -d "$WP_TESTS_DIR/includes" ]; then
    return
  fi

  if [ ! -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
    echo "Error: WordPress version file not found in $WP_CORE_DIR" >&2
    exit 1
  fi

  local wp_version
  wp_version=$(grep -oP "\\\$wp_version = '\\K[^']+" "$WP_CORE_DIR/wp-includes/version.php" || true)
  if [ -z "$wp_version" ]; then
    echo "Error: Could not determine WordPress version." >&2
    exit 1
  fi

  if ! command -v svn >/dev/null 2>&1; then
    echo "Error: svn is required to install the WordPress test suite." >&2
    exit 1
  fi

  echo "Installing WordPress ${wp_version} test suite ..."
  mkdir -p "$WP_TESTS_DIR"
  svn co --quiet "https://develop.svn.wordpress.org/tags/${wp_version}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
  svn co --quiet "https://develop.svn.wordpress.org/tags/${wp_version}/tests/phpunit/data/" "$WP_TESTS_DIR/data"

  local sample_config="$WP_TESTS_DIR/includes/wp-tests-config-sample.php"
  if [ ! -f "$sample_config" ]; then
    # Newer releases keep the sample config at the test-suite repository root.
    svn export --quiet "https://develop.svn.wordpress.org/tags/${wp_version}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config-sample.php"
    sample_config="$WP_TESTS_DIR/wp-tests-config-sample.php"
  fi

  cp "$sample_config" "$WP_TESTS_DIR/wp-tests-config.php"

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
