<?php declare( strict_types=1 );

namespace Hardenly\Scanner\Checks;

use Hardenly\Scanner\Check;
use Hardenly\Scanner\Checks\Code\SuspiciousCodeCheck;
use Hardenly\Scanner\Checks\Online\CoreChecksumsCheck;
use Hardenly\Scanner\Checks\Online\PluginChecksumsCheck;
use Hardenly\Scanner\Checks\Online\VulnerabilityCheck;
use Hardenly\Scanner\Checks\Config\DebugDisplayCheck;
use Hardenly\Scanner\Checks\Config\EnvironmentTypeCheck;
use Hardenly\Scanner\Checks\Config\FileEditCheck;
use Hardenly\Scanner\Checks\Config\ForceSslAdminCheck;
use Hardenly\Scanner\Checks\Config\SaltsCheck;
use Hardenly\Scanner\Checks\Database\AutoloadSizeCheck;
use Hardenly\Scanner\Checks\Database\TablePrefixCheck;
use Hardenly\Scanner\Checks\Files\BackupFilesCheck;
use Hardenly\Scanner\Checks\Files\DebugLogCheck;
use Hardenly\Scanner\Checks\Files\ExposedFilesCheck;
use Hardenly\Scanner\Checks\Files\LeftoverFilesCheck;
use Hardenly\Scanner\Checks\Files\PermissionsCheck;
use Hardenly\Scanner\Checks\Files\RecentlyModifiedCheck;
use Hardenly\Scanner\Checks\Files\UploadsPhpCheck;
use Hardenly\Scanner\Checks\Server\CertificateCheck;
use Hardenly\Scanner\Checks\Server\DirectoryListingCheck;
use Hardenly\Scanner\Checks\Server\HttpsCheck;
use Hardenly\Scanner\Checks\Server\SecurityHeadersCheck;
use Hardenly\Scanner\Checks\Server\WpCronCheck;
use Hardenly\Scanner\Checks\Server\XmlRpcCheck;
use Hardenly\Scanner\Checks\Users\AdminCountCheck;
use Hardenly\Scanner\Checks\Users\AdminUsernameCheck;
use Hardenly\Scanner\Checks\Users\InactiveAdminsCheck;
use Hardenly\Scanner\Checks\Users\UserEnumerationCheck;
use Hardenly\Scanner\Checks\Versions\CoreVersionCheck;
use Hardenly\Scanner\Checks\Versions\InactiveExtensionsCheck;
use Hardenly\Scanner\Checks\Versions\PhpVersionCheck;
use Hardenly\Scanner\Checks\Versions\PluginUpdatesCheck;
use Hardenly\Scanner\Checks\Versions\ThemeUpdatesCheck;
use Hardenly\Site\Constants;
use Hardenly\Site\Filesystem;
use Hardenly\Site\Loopback;
use Hardenly\Users\LastLogin;
use Hardenly\Vulnerability\ProviderRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * The checks that ship with Hardenly. The registry orders them by group.
 */
class BuiltInChecks {
	/**
	 * @param LastLogin        $last_login Login records.
	 * @param ProviderRegistry $providers  Vulnerability providers.
	 *
	 * @return Check[]
	 */
	public static function all( LastLogin $last_login, ProviderRegistry $providers ): array {
		$constants  = new Constants();
		$filesystem = new Filesystem();
		$loopback   = new Loopback();

		return [
			new DebugDisplayCheck( $constants ),
			new FileEditCheck( $constants ),
			new ForceSslAdminCheck( $constants ),
			new SaltsCheck( $constants ),
			new EnvironmentTypeCheck(),
			new AdminUsernameCheck(),
			new AdminCountCheck(),
			new InactiveAdminsCheck( $last_login ),
			new UserEnumerationCheck( $loopback ),
			new CoreVersionCheck(),
			new PluginUpdatesCheck(),
			new ThemeUpdatesCheck(),
			new PhpVersionCheck(),
			new InactiveExtensionsCheck(),
			new HttpsCheck(),
			new CertificateCheck(),
			new SecurityHeadersCheck( $loopback ),
			new DirectoryListingCheck( $loopback ),
			new XmlRpcCheck( $loopback ),
			new WpCronCheck( $constants ),
			new TablePrefixCheck(),
			new AutoloadSizeCheck(),
			new PermissionsCheck( $filesystem ),
			new LeftoverFilesCheck( $filesystem ),
			new BackupFilesCheck( $filesystem, $constants ),
			new DebugLogCheck( $filesystem, $constants, $loopback ),
			new ExposedFilesCheck( $filesystem, $loopback ),
			new UploadsPhpCheck( $filesystem ),
			new RecentlyModifiedCheck( $filesystem ),
			new SuspiciousCodeCheck( $filesystem ),
			new CoreChecksumsCheck( null, $filesystem ),
			new PluginChecksumsCheck( null, $filesystem, $constants ),
			new VulnerabilityCheck( $providers ),
		];
	}
}
