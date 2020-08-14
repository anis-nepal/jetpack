<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Autoloader Generator.
 *
 * @package automattic/jetpack-autoloader
 */

// phpcs:disable PHPCompatibility.Keywords.NewKeywords.t_useFound
// phpcs:disable PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ns_separatorFound
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewClosure.Found
// phpcs:disable PHPCompatibility.Keywords.NewKeywords.t_namespaceFound
// phpcs:disable PHPCompatibility.Keywords.NewKeywords.t_dirFound
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fopen
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.InterpolatedVariableNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase


namespace Automattic\Jetpack\Autoloader;

use Composer\Autoload\AutoloadGenerator as BaseGenerator;
use Composer\Autoload\ClassMapGenerator;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

/**
 * Class AutoloadGenerator.
 */
class AutoloadGenerator extends BaseGenerator {

	const COMMENT = <<<AUTOLOADER_COMMENT
/**
 * This file was automatically generated by automattic/jetpack-autoloader.
 *
 * @package automattic/jetpack-autoloader
 */

AUTOLOADER_COMMENT;

	/**
	 * The filesystem utility.
	 *
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * Instantiate an AutoloadGenerator object.
	 *
	 * @param IOInterface $io IO object.
	 */
	public function __construct( IOInterface $io = null ) {
		$this->io         = $io;
		$this->filesystem = new Filesystem();
	}

	/**
	 * Dump the Jetpack autoloader files.
	 *
	 * @param Config                       $config Config object.
	 * @param InstalledRepositoryInterface $localRepo Installed Reposetories object.
	 * @param PackageInterface             $mainPackage Main Package object.
	 * @param InstallationManager          $installationManager Manager for installing packages.
	 * @param string                       $targetDir Path to the current target directory.
	 * @param bool                         $scanPsrPackages Whether or not PSR packages should be converted to a classmap.
	 * @param string                       $suffix The autoloader suffix.
	 */
	public function dump(
		Config $config,
		InstalledRepositoryInterface $localRepo,
		PackageInterface $mainPackage,
		InstallationManager $installationManager,
		$targetDir,
		$scanPsrPackages = false,
		$suffix = null
	) {
		$this->filesystem->ensureDirectoryExists( $config->get( 'vendor-dir' ) );

		$packageMap = $this->buildPackageMap( $installationManager, $mainPackage, $localRepo->getCanonicalPackages() );
		$autoloads  = $this->parseAutoloads( $packageMap, $mainPackage );

		// Convert the autoloads into a format that the manifest generator can consume more easily.
		$basePath           = $this->filesystem->normalizePath( realpath( getcwd() ) );
		$vendorPath         = $this->filesystem->normalizePath( realpath( $config->get( 'vendor-dir' ) ) );
		$processedAutoloads = $this->processAutoloads( $autoloads, $scanPsrPackages, $vendorPath, $basePath );
		unset( $packageMap, $autoloads );

		// Write all of the files now that we're done.
		$this->writeAutoloaderFiles( $vendorPath . '/jetpack-autoloader/', $suffix );
		$this->writeManifests( $vendorPath . '/' . $targetDir, $processedAutoloads );
	}

	/**
	 * This function differs from the composer parseAutoloadsType in that beside returning the path.
	 * It also return the path and the version of a package.
	 *
	 * Currently supports only psr-4 and clasmap parsing.
	 *
	 * @param array            $packageMap Map of all the packages.
	 * @param string           $type Type of autoloader to use, currently not used, since we only support psr-4.
	 * @param PackageInterface $mainPackage Instance of the Package Object.
	 *
	 * @return array
	 */
	protected function parseAutoloadsType( array $packageMap, $type, PackageInterface $mainPackage ) {
		$autoloads = array();

		if ( 'psr-4' !== $type && 'classmap' !== $type && 'files' !== $type ) {
			return parent::parseAutoloadsType( $packageMap, $type, $mainPackage );
		}

		foreach ( $packageMap as $item ) {
			list($package, $installPath) = $item;
			$autoload                    = $package->getAutoload();

			if ( $package === $mainPackage ) {
				$autoload = array_merge_recursive( $autoload, $package->getDevAutoload() );
			}

			if ( null !== $package->getTargetDir() && $package !== $mainPackage ) {
				$installPath = substr( $installPath, 0, -strlen( '/' . $package->getTargetDir() ) );
			}

			if ( 'psr-4' === $type && isset( $autoload['psr-4'] ) && is_array( $autoload['psr-4'] ) ) {
				foreach ( $autoload['psr-4'] as $namespace => $paths ) {
					$paths = is_array( $paths ) ? $paths : array( $paths );
					foreach ( $paths as $path ) {
						$relativePath              = empty( $installPath ) ? ( empty( $path ) ? '.' : $path ) : $installPath . '/' . $path;
						$autoloads[ $namespace ][] = array(
							'path'    => $relativePath,
							'version' => $package->getVersion(), // Version of the class comes from the package - should we try to parse it?
						);
					}
				}
			}

			if ( 'classmap' === $type && isset( $autoload['classmap'] ) && is_array( $autoload['classmap'] ) ) {
				foreach ( $autoload['classmap'] as $paths ) {
					$paths = is_array( $paths ) ? $paths : array( $paths );
					foreach ( $paths as $path ) {
						$relativePath = empty( $installPath ) ? ( empty( $path ) ? '.' : $path ) : $installPath . '/' . $path;
						$autoloads[]  = array(
							'path'    => $relativePath,
							'version' => $package->getVersion(), // Version of the class comes from the package - should we try to parse it?
						);
					}
				}
			}
			if ( 'files' === $type && isset( $autoload['files'] ) && is_array( $autoload['files'] ) ) {
				foreach ( $autoload['files'] as $paths ) {
					$paths = is_array( $paths ) ? $paths : array( $paths );
					foreach ( $paths as $path ) {
						$relativePath = empty( $installPath ) ? ( empty( $path ) ? '.' : $path ) : $installPath . '/' . $path;
						$autoloads[ $this->getFileIdentifier( $package, $path ) ] = array(
							'path'    => $relativePath,
							'version' => $package->getVersion(), // Version of the file comes from the package - should we try to parse it?
						);
					}
				}
			}
		}

		return $autoloads;
	}

	/**
	 * Given Composer's autoloads this will convert them to a version that we can use to generate the manifests.
	 *
	 * @param array  $autoloads The autoloads we want to process.
	 * @param bool   $scanPsrPackages Whether or not PSR packages should be converted to a classmap.
	 * @param string $vendorPath The path to the vendor directory.
	 * @param string $basePath The path to the current directory.
	 *
	 * @return array $processedAutoloads
	 */
	private function processAutoloads( $autoloads, $scanPsrPackages, $vendorPath, $basePath ) {
		$processor = new AutoloadProcessor(
			function ( $path, $classBlacklist, $namespace ) use ( $basePath ) {
				$dir = $this->filesystem->normalizePath(
					$this->filesystem->isAbsolutePath( $path ) ? $path : $basePath . '/' . $path
				);
				return ClassMapGenerator::createMap(
					$dir,
					$classBlacklist,
					null, // Don't pass the IOInterface since the normal autoload generation will have reported already.
					empty( $namespace ) ? null : $namespace
				);
			},
			function ( $path ) use ( $basePath, $vendorPath ) {
				return $this->getPathCode( $this->filesystem, $basePath, $vendorPath, $path );
			}
		);

		return array(
			'psr-4'    => $processor->processPsr4Packages( $autoloads, $scanPsrPackages ),
			'classmap' => $processor->processClassmap( $autoloads, $scanPsrPackages ),
			'files'    => $processor->processFiles( $autoloads ),
		);
	}

	/**
	 * Writes all of the autoloader files to disk.
	 *
	 * @param string $outDir The directory to write to.
	 * @param string $suffix The unique autoloader suffix.
	 */
	private function writeAutoloaderFiles( $outDir, $suffix ) {
		$this->io->writeError( "<info>Generating jetpack autoloader ($outDir)</info>" );

		// We will remove all autoloader files to generate this again.
		$this->filesystem->emptyDirectory( $outDir );

		$packageFiles = array(
			'autoload.php'                 => '../autoload_packages.php',
			'functions.php'                => 'autoload_functions.php',
			'class-autoloader-locator.php' => null,
			'class-autoloader-handler.php' => null,
			'class-manifest-handler.php'   => null,
			'class-plugins-handler.php'    => null,
			'class-version-selector.php'   => null,
			'class-version-loader.php'     => null,
		);

		foreach ( $packageFiles as $file => $newFile ) {
			$newFile = isset( $newFile ) ? $newFile : $file;

			$content = $this->getAutoloadPackageFile( $file, $suffix );

			if ( file_put_contents( $outDir . '/' . $newFile, $content ) ) {
				$this->io->writeError( "  <info>Generated: $newFile</info>" );
			} else {
				$this->io->writeError( "  <error>Error: $newFile</error>" );
			}
		}
	}

	/**
	 * Writes all of the manifest files to disk.
	 *
	 * @param string $outDir The directory to write to.
	 * @param array  $processedAutoloads The processed autoloads.
	 */
	private function writeManifests( $outDir, $processedAutoloads ) {
		$this->io->writeError( "<info>Generating jetpack autoloader manifests ($outDir)</info>" );

		$manifestFiles = array(
			'classmap' => 'jetpack_autoload_classmap.php',
			'psr-4'    => 'jetpack_autoload_psr4.php',
			'files'    => 'jetpack_autoload_files.php',
		);

		foreach ( $manifestFiles as $key => $file ) {
			// Make sure the file doesn't exist so it isn't there if we don't write it.
			$this->filesystem->remove( $outDir . '/' . $file );
			if ( empty( $processedAutoloads[ $key ] ) ) {
				continue;
			}

			$content = ManifestGenerator::buildManifest( $key, $file, $processedAutoloads[ $key ] );
			if ( empty( $content ) ) {
				continue;
			}

			if ( file_put_contents( $outDir . '/' . $file, $content ) ) {
				$this->io->writeError( "  <info>Generated: $file</info>" );
			} else {
				$this->io->writeError( "  <error>Error: $file</error>" );
			}
		}
	}

	/**
	 * Generate the PHP that will be used in the autoload_packages.php files.
	 *
	 * @param String $filename a file to prepare.
	 * @param String $suffix   Unique suffix used in the namespace.
	 *
	 * @return string
	 */
	private function getAutoloadPackageFile( $filename, $suffix ) {
		$header  = self::COMMENT;
		$header .= PHP_EOL;
		$header .= 'namespace Automattic\Jetpack\Autoloader\jp' . $suffix . ';';
		$header .= PHP_EOL . PHP_EOL;

		$sourceLoader  = fopen( __DIR__ . '/' . $filename, 'r' );
		$file_contents = stream_get_contents( $sourceLoader );
		return str_replace(
			'/* HEADER */',
			$header,
			$file_contents
		);
	}
}
