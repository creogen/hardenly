<?php declare( strict_types=1 );

namespace Sitewarden\Scanner\Checks;

use Sitewarden\Scanner\Check;
use Sitewarden\Scanner\Checks\Code\SuspiciousCodeCheck;
use Sitewarden\Scanner\Checks\Online\CoreChecksumsCheck;
use Sitewarden\Scanner\Checks\Online\PluginChecksumsCheck;
use Sitewarden\Scanner\Checks\Online\VulnerabilityCheck;
use Sitewarden\Scanner\Checks\Config\DebugDisplayCheck;
use Sitewarden\Scanner\Checks\Config\EnvironmentTypeCheck;
use Sitewarden\Scanner\Checks\Config\FileEditCheck;
use Sitewarden\Scanner\Checks\Config\ForceSslAdminCheck;
use Sitewarden\Scanner\Checks\Config\SaltsCheck;
use Sitewarden\Scanner\Checks\Database\AutoloadSizeCheck;
use Sitewarden\Scanner\Checks\Database\TablePrefixCheck;
use Sitewarden\Scanner\Checks\Files\BackupFilesCheck;
use Sitewarden\Scanner\Checks\Files\DebugLogCheck;
use Sitewarden\Scanner\Checks\Files\ExposedFilesCheck;
use Sitewarden\Scanner\Checks\Files\LeftoverFilesCheck;
use Sitewarden\Scanner\Checks\Files\PermissionsCheck;
use Sitewarden\Scanner\Checks\Files\RecentlyModifiedCheck;
use Sitewarden\Scanner\Checks\Files\UploadsPhpCheck;
use Sitewarden\Scanner\Checks\Server\CertificateCheck;
use Sitewarden\Scanner\Checks\Server\DirectoryListingCheck;
use Sitewarden\Scanner\Checks\Server\HttpsCheck;
use Sitewarden\Scanner\Checks\Server\SecurityHeadersCheck;
use Sitewarden\Scanner\Checks\Server\WpCronCheck;
use Sitewarden\Scanner\Checks\Server\XmlRpcCheck;
use Sitewarden\Scanner\Checks\Users\AdminCountCheck;
use Sitewarden\Scanner\Checks\Users\AdminUsernameCheck;
use Sitewarden\Scanner\Checks\Users\InactiveAdminsCheck;
use Sitewarden\Scanner\Checks\Users\UserEnumerationCheck;
use Sitewarden\Scanner\Checks\Versions\CoreVersionCheck;
use Sitewarden\Scanner\Checks\Versions\InactiveExtensionsCheck;
use Sitewarden\Scanner\Checks\Versions\PhpVersionCheck;
use Sitewarden\Scanner\Checks\Versions\PluginUpdatesCheck;
use Sitewarden\Scanner\Checks\Versions\ThemeUpdatesCheck;
use Sitewarden\Site\Constants;
use Sitewarden\Site\Filesystem;
use Sitewarden\Site\Loopback;
use Sitewarden\Users\LastLogin;
use Sitewarden\Vulnerability\ProviderRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * The checks that ship with Sitewarden. The registry orders them by group.
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
