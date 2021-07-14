<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Composer;


use Baraja\Console\Helpers as ConsoleHelpers;
use Baraja\PackageManager\Helpers;
use Baraja\PackageManager\PackageRegistrator;
use Nette\Neon\Neon;

/**
 * Priority: 1000
 */
final class ConfigLocalNeonTask extends BaseTask
{
	/**
	 * This credentials will be automatically used for test connection.
	 * If connection works it will be used for final Neon configuration.
	 *
	 * @var array<string, array<int, array<int, string>>>
	 */
	private static array $commonCredentials = [
		'localhost' => [
			['root', 'root'],
			['root', 'password'],
			['root', ''],
		],
		'127.0.0.1' => [
			['root', 'root'],
			['root', 'password'],
			['root', ''],
		],
	];


	/**
	 * Create configuration local.neon file with minimal default settings.
	 *
	 * 1. Check if local.neon exist
	 * 2. In case of CI environment generate default content
	 * 3. In other cases as user for configuration data.
	 */
	public function run(): bool
	{
		$path = \dirname(__DIR__, 6) . '/app/config/local.neon';
		try {
			$doctrineExist = interface_exists('Doctrine\ORM\EntityManagerInterface');
		} catch (\Throwable) {
			$doctrineExist = false;
		}
		if (is_file($path) === true) {
			echo 'local.neon exist.' . "\n";
			echo 'Path: ' . $path . "\n";
			$localNeonContent = trim((string) file_get_contents($path));
			if ($localNeonContent !== '' || $doctrineExist === false) {
				return true;
			}
			echo 'Configuration is empty.' . "\n";
		}
		if ($_ENV !== []) {
			echo 'Auto detected environment settings: ' . "\n";
			foreach ($_ENV as $key => $value) {
				echo '    ' . ConsoleHelpers::terminalRenderLabel((string) $key) . ': ';
				echo (is_scalar($value) ? $value : json_encode($value, JSON_PRETTY_PRINT)) . "\n";
			}
			echo "\n\n";
		}
		$connectionString = $_ENV['DB_URI'] ?? null;
		if ($connectionString !== null) {
			echo 'Use connection string.';
			file_put_contents($path, '');

			return true;
		}
		echo 'Environment variable "DB_URI" does not exist.' . "\n";
		if ($doctrineExist === false) {
			echo 'Doctrine not found: Using empty configuration file.';
			file_put_contents($path, '');

			return true;
		}

		try {
			if (PackageRegistrator::getCiDetect() !== null) {
				echo 'CI environment detected: Use default configuration.' . "\n";
				echo 'Path: ' . $path;
				file_put_contents($path, Neon::encode($this->getDefaultTestConfiguration(), Neon::BLOCK));

				return true;
			}
			echo 'CI environment has not detected.' . "\n";
		} catch (\Exception) {
			// Silence is golden.
		}

		echo "\n-----\n";
		echo 'local.neon does not exist.' . "\n";
		echo 'Path: ' . $path;

		if ($this->ask('Create?', ['y', 'n']) === 'y') {
			file_put_contents($path, Neon::encode(
				$this->generateMySqlConfig(),
				Neon::BLOCK,
			));
		}

		return true;
	}


	public function getName(): string
	{
		return 'Local.neon checker';
	}


	/**
	 * @return array{'baraja.database': array{connection: array{host: string, dbname: string, user: string, password: string}}}
	 */
	private function generateMySqlConfig(): array
	{
		$mySqlCredentials = $this->mySqlConnect();
		$createConnection = fn(): \PDO => new \PDO(
			'mysql:host=' . $mySqlCredentials['server'],
			$mySqlCredentials['user'],
			$mySqlCredentials['password'],
		);

		$databaseList = [];
		$databaseCounter = 1;
		$usedDatabase = null;
		$candidateDatabases = [];
		echo "\n\n";

		$showDatabasesSelection = $createConnection()->query('SHOW DATABASES');
		if ($showDatabasesSelection !== false) {
			$databaseSelectionList = $showDatabasesSelection->fetchAll() ?: [];
		} else {
			$databaseSelectionList = [];
		}
		foreach ($databaseSelectionList as $database) {
			echo $databaseCounter . ': ' . $database[0] . "\n";
			$databaseList[$databaseCounter] = $database[0];
			$databaseCounter++;
			if ($database[0] !== 'information_schema') {
				$candidateDatabases[] = $database[0];
			}
		}
		if (\count($candidateDatabases) === 1) {
			$usedDatabase = $candidateDatabases[0];
		}
		while (true) {
			if ($usedDatabase === null) {
				$usedDatabase = (string) $this->ask('Which database use? Type number or specific name. Type "new" for create new.');
				if (preg_match('/^\d+$/', $usedDatabase)) {
					$usedDatabaseKey = (int) $usedDatabase;
					if (isset($databaseList[$usedDatabaseKey])) {
						$usedDatabase = $databaseList[$usedDatabaseKey];
						break;
					}

					echo 'Selection "' . $usedDatabase . '" is out of range.' . "\n";
				}
			}
			if (\in_array($usedDatabase, $databaseList, true)) {
				break;
			}
			if (strtolower($usedDatabase) === 'new') {
				while (true) {
					$usedDatabase = (string) $this->ask('How is the database name?');
					if (preg_match('/^[a-z0-9_\-]+$/', $usedDatabase)) {
						if (!\in_array($usedDatabase, $databaseList, true)) {
							$this->createDatabase($usedDatabase, $createConnection);
							break;
						}

						echo 'Database "' . $usedDatabase . '" already exist.' . "\n\n";
					} else {
						Helpers::terminalRenderError('Invalid database name. You can use only a-z, 0-9, "-" and "_".');
						echo "\n\n";
					}
				}
				break;
			}
			if (preg_match('/^[a-zA-Z0-9_\-]+$/', $usedDatabase)) {
				$useHint = false;
				foreach ($databaseList as $possibleDatabase) {
					if (strncmp($possibleDatabase, $usedDatabase, strlen($usedDatabase)) === 0) {
						$checkDatabase = $possibleDatabase;
						if ($this->ask('Use database "' . $checkDatabase . '"?', ['y', 'n']) === 'y') {
							$usedDatabase = $checkDatabase;
							$useHint = true;
							break;
						}
					}
				}
				if ($useHint === true) {
					break;
				}
				echo 'Database "' . $usedDatabase . '" does not exist.' . "\n";
				$newDatabaseName = strtolower($usedDatabase);
				if ($this->ask('Create database "' . $newDatabaseName . '"?', ['y', 'n']) === 'y') {
					$this->createDatabase($newDatabaseName, $createConnection);
					break;
				}
			}

			echo 'Invalid database selection. Please use number in range (1 - ' . ($databaseCounter - 1) . ') or NEW.';
		}

		return [
			'baraja.database' => [
				'connection' => [
					'host' => $mySqlCredentials['server'],
					'dbname' => $usedDatabase,
					'user' => $mySqlCredentials['user'],
					'password' => $mySqlCredentials['password'],
				],
			],
		];
	}


	/**
	 * Get mysql connection credentials and return fully works credentials or in case of error empty array.
	 *
	 * @return array{server: string, user: string, password: string}
	 */
	private function mySqlConnect(): array
	{
		$dbh = null;
		$connectionServer = null;
		$connectionUser = null;
		$connectionPassword = null;

		foreach (self::$commonCredentials as $server => $credentials) {
			foreach ($credentials as $credential) {
				try {
					$dbh = new \PDO('mysql:host=' . $server, $credential[0], $credential[1]);
					$connectionServer = $server;
					[$connectionUser, $connectionPassword] = $credential;
					break;
				} catch (\PDOException) {
					// Connection does not work.
				}
			}
		}

		if ($dbh !== null) {
			echo '+--- Functional connections have been found automatically.' . "\n";
			echo '| Server: ' . \json_encode($connectionServer) . "\n";
			echo '| User: ' . \json_encode($connectionUser) . "\n";
			echo '| Password: ' . \json_encode($connectionPassword) . "\n";

			if ($this->ask('Use this configuration?', ['y', 'n']) === 'y') {
				return [
					'server' => (string) $connectionServer,
					'user' => (string) $connectionUser,
					'password' => (string) $connectionPassword,
				];
			}
		}

		for ($ttl = 10; $ttl > 0; $ttl--) {
			$connectionServer = $this->ask('Server (hostname) [empty for "127.0.0.1"]:');
			if ($connectionServer === null) {
				echo 'Server "127.0.0.1" has been used.';
				$connectionServer = '127.0.0.1';
			}
			$connectionUser = $this->ask('User [empty for "root"]:');
			if ($connectionUser === null) {
				echo 'User "root" has been used.';
				$connectionUser = 'root';
			}
			do {
				$connectionPassword = trim($this->ask('Password [can not be empty!]:') ?? '');
				if ($connectionPassword !== '') {
					break;
				}
				Helpers::terminalRenderError('Password can not be empty!');
				echo "\n\n\n" . 'Information to resolve this issue:' . "\n\n";
				echo 'For the best protection of the web server and database,' . "\n";
				echo 'it is important to always set a passphrase that must not be an empty string.' . "\n";
				echo 'I>f you are using a database without a password, set the password first and then install again.';
			} while (true);
			echo "\n\n";

			try {
				new \PDO('mysql:host=' . $connectionServer, $connectionUser, $connectionPassword);

				return [
					'server' => $connectionServer,
					'user' => $connectionUser,
					'password' => $connectionPassword,
				];
			} catch (\PDOException $e) {
				Helpers::terminalRenderError('Connection does not work!');
				echo "\n";
				Helpers::terminalRenderError($e->getMessage());
				echo "\n\n";
			}
		}

		throw new \LogicException('MySql connection credentials can not be resolved.');
	}


	private function createDatabase(string $name, callable $createConnection): void
	{
		$sql = 'CREATE DATABASE IF NOT EXISTS `' . $name . '`; ' . "\n"
			. 'DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci';

		echo 'Creating database...' . "\n";
		echo 'Command: ' . $sql . "\n\n";

		/** @var \PDO $connection */
		$connection = $createConnection();
		if ($connection->exec($sql) !== 1) {
			Helpers::terminalRenderError('Can not create database!');
			echo "\n\n";

			return;
		}

		$checkSql = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \'' . $name . '\'';

		echo 'Database was successfully created.' . "\n\n";
		echo 'Testing database...' . "\n";
		echo 'Command: ' . $checkSql . "\n\n";
		echo 'Creating database...' . "\n";

		/** @var \PDO $connection */
		$connection = $createConnection();
		if ($connection->exec($checkSql) === 1) {
			echo 'Database has been created.' . "\n";
			echo 'Checking...' . "\n";
			sleep(1);
			echo 'Done.' . "\n\n";
		} else {
			Helpers::terminalRenderError('Can not create database. Please create manually and return here.');
			echo "\n\n";
			die;
		}
	}


	/**
	 * Default configuration for CI and test environment.
	 *
	 * @return array{'baraja.database': array{connection: array{url: string}}}
	 */
	private function getDefaultTestConfiguration(): array
	{
		if (
			!function_exists('sqlite_open')
			&& !class_exists('SQLite3')
			&& !extension_loaded('sqlite3')
		) {
			trigger_error(
				'Extension Sqlite3 may not be available for test cases. '
				. 'Please check your environment configuration.',
			);
		}

		return [
			'baraja.database' => [
				'connection' => [
					'url' => 'sqlite:///:memory:',
				],
			],
		];
	}
}
