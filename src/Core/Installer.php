<?php

namespace BestConnectOnboarding\Core;

defined('ABSPATH') || exit;

class Installer
{

	const DB_VERSION = '1.10.0';

	private const DB_VERSION_OPTION =
	'bcob_db_version';

	private const UPGRADE_LOCK_OPTION =
	'bcob_db_upgrade_lock';

	public function __construct()
	{
		register_activation_hook(
			BCOB_DIR,
			[$this, 'install']
		);

		$this->maybe_upgrade();
	}

	public function install(): void
	{
		$installed_version =
			(string) get_option(
				self::DB_VERSION_OPTION,
				''
			);

		/*
     * Only use CREATE TABLE SQL for a genuinely
     * fresh installation.
     *
     * Existing installations must be modified only
     * through explicit migrations.
     */
		if (!$this->has_existing_database()) {
			$this->create_tables();

			$installed_version =
				'0.0.0';
		}

		if (
			$this->run_migrations(
				$installed_version !== ''
					? $installed_version
					: '0.0.0'
			)
		) {
			update_option(
				self::DB_VERSION_OPTION,
				self::DB_VERSION
			);
		}
	}

	private function maybe_upgrade(): void
	{
		$installed_version =
			(string) get_option(
				self::DB_VERSION_OPTION,
				''
			);

		if (
			$installed_version !== '' &&
			version_compare(
				$installed_version,
				self::DB_VERSION,
				'>='
			)
		) {
			return;
		}

		/*
     * On first plugin load before activation,
     * tables do not exist yet.
     *
     * Activation will call install().
     */
		if (!$this->has_existing_database()) {
			return;
		}

		if (!$this->acquire_upgrade_lock()) {
			return;
		}

		try {
			/*
         * Read it again after obtaining the lock.
         * Another request may already have upgraded it.
         */
			$installed_version =
				(string) get_option(
					self::DB_VERSION_OPTION,
					''
				);

			if (
				$installed_version !== '' &&
				version_compare(
					$installed_version,
					self::DB_VERSION,
					'>='
				)
			) {
				return;
			}

			if (
				$this->run_migrations(
					$installed_version !== ''
						? $installed_version
						: '0.0.0'
				)
			) {
				update_option(
					self::DB_VERSION_OPTION,
					self::DB_VERSION
				);
			}
		} finally {
			$this->release_upgrade_lock();
		}
	}

	private function run_migrations(
		string $from_version
	): bool {
		if (
			version_compare(
				$from_version,
				'1.7.0',
				'<'
			)
		) {
			if (
				!$this->migrate_to_1_7_0()
			) {
				return false;
			}
		}

		if (
			version_compare(
				$from_version,
				'1.8.0',
				'<'
			)
		) {
			if (
				!$this->migrate_to_1_8_0()
			) {
				return false;
			}
		}

		if (
			version_compare(
				$from_version,
				'1.9.0',
				'<'
			)
		) {
			if (
				!$this->migrate_to_1_9_0()
			) {
				return false;
			}
		}

		if (
			version_compare(
				$from_version,
				'1.10.0',
				'<'
			)
		) {
			if (
				!$this->migrate_to_1_10_0()
			) {
				return false;
			}
		}

		return true;
	}

	private function migrate_to_1_7_0(): bool
	{
		global $wpdb;

		$leads_table =
			$wpdb->prefix .
			'bcob_leads';

		$payments_table =
			$wpdb->prefix .
			'bcob_payments';

		if (
			!$this->table_exists(
				$leads_table
			) ||
			!$this->table_exists(
				$payments_table
			)
		) {
			return false;
		}

		/*
     * Leads.
     */
		if (
			!$this->add_column_if_missing(
				$leads_table,
				'membership_number',
				'VARCHAR(100) DEFAULT NULL'
			)
		) {
			return false;
		}

		if (
			!$this->add_index_if_missing(
				$leads_table,
				'membership_number',
				'membership_number'
			)
		) {
			return false;
		}

		if (
			!$this->ensure_lead_status_column(
				$leads_table
			)
		) {
			return false;
		}

		/*
     * Payments.
     */
		$payment_columns = [
			'payment_type' =>
			"VARCHAR(30) NOT NULL DEFAULT 'one_time'",

			'payment_intent_id' =>
			'VARCHAR(255) DEFAULT NULL',

			'subscription_id' =>
			'VARCHAR(255) DEFAULT NULL',

			'customer_id' =>
			'VARCHAR(255) DEFAULT NULL',

			'price_id' =>
			'VARCHAR(255) DEFAULT NULL',

			'product_id' =>
			'VARCHAR(255) DEFAULT NULL',

			'invoice_id' =>
			'VARCHAR(255) DEFAULT NULL',
		];

		foreach (
			$payment_columns
			as $column => $definition
		) {
			if (
				!$this->add_column_if_missing(
					$payments_table,
					$column,
					$definition
				)
			) {
				return false;
			}
		}

		$payment_indexes = [
			'payment_intent_id',
			'subscription_id',
			'customer_id',
			'price_id',
			'product_id',
			'invoice_id',
		];

		foreach (
			$payment_indexes
			as $index
		) {
			if (
				!$this->add_index_if_missing(
					$payments_table,
					$index,
					$index
				)
			) {
				return false;
			}
		}

		/*
     * Legacy payment migration.
     *
     * Old versions stored Stripe PaymentIntent IDs
     * only inside transaction_id.
     *
     * Copy ONLY values that are clearly Stripe
     * PaymentIntent IDs.
     *
     * Existing non-empty payment_intent_id values
     * are NEVER overwritten.
     */
		$result =
			$wpdb->query(
				"UPDATE {$payments_table}
            SET payment_intent_id = transaction_id
            WHERE
                (
                    payment_intent_id IS NULL
                    OR payment_intent_id = ''
                )
            AND transaction_id IS NOT NULL
            AND transaction_id <> ''
            AND LEFT(transaction_id, 3) = 'pi_'"
			);

		if ($result === false) {
			return false;
		}

		return true;
	}

	private function migrate_to_1_8_0(): bool
	{
		global $wpdb;

		$payments_table =
			$wpdb->prefix .
			'bcob_payments';

		if (
			!$this->table_exists(
				$payments_table
			)
		) {
			return false;
		}

		$columns = [
			'membership_id' =>
			'BIGINT UNSIGNED DEFAULT NULL',

			'portal_index' =>
			'INT UNSIGNED DEFAULT NULL',

			'price_snapshot' =>
			'LONGTEXT DEFAULT NULL',
		];

		foreach (
			$columns as $column => $definition
		) {
			if (
				!$this->add_column_if_missing(
					$payments_table,
					$column,
					$definition
				)
			) {
				return false;
			}
		}

		if (
			!$this->add_index_if_missing(
				$payments_table,
				'membership_id',
				'membership_id'
			)
		) {
			return false;
		}

		if (
			!$this->create_logs_table_if_missing()
		) {
			return false;
		}

		return true;
	}

	private function migrate_to_1_9_0(): bool
	{
		global $wpdb;

		$prefill_table =
			$wpdb->prefix .
			'bcob_prefill_links';

		if (
			!$this->table_exists(
				$prefill_table
			)
		) {
			return false;
		}

		$columns = [
			'status' =>
			"VARCHAR(20) NOT NULL DEFAULT 'active'",

			'claimed_at' =>
			'DATETIME DEFAULT NULL',

			'used_at' =>
			'DATETIME DEFAULT NULL',
		];

		foreach (
			$columns as $column => $definition
		) {
			if (
				!$this->add_column_if_missing(
					$prefill_table,
					$column,
					$definition
				)
			) {
				return false;
			}
		}

		if (
			!$this->add_index_if_missing(
				$prefill_table,
				'status',
				'status'
			)
		) {
			return false;
		}

		return true;
	}

	private function migrate_to_1_10_0(): bool
	{
		return $this->create_logs_table_if_missing();
	}

	private function create_logs_table_if_missing(): bool
	{
		global $wpdb;

		$table =
			$wpdb->prefix .
			'bcob_logs';

		if (
			$this->table_exists(
				$table
			)
		) {
			return true;
		}

		$charset_collate =
			$wpdb->get_charset_collate();

		$result =
			$wpdb->query(
				"CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                level VARCHAR(20) NOT NULL,
                channel VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                operation VARCHAR(100) DEFAULT NULL,
                operation_id CHAR(36) NOT NULL,
                form_id BIGINT UNSIGNED DEFAULT NULL,
                lead_id BIGINT UNSIGNED DEFAULT NULL,
                payment_id BIGINT UNSIGNED DEFAULT NULL,
                context LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY level (level),
                KEY channel (channel),
                KEY operation (operation),
                KEY operation_id (operation_id),
                KEY form_id (form_id),
                KEY lead_id (lead_id),
                KEY payment_id (payment_id),
                KEY created_at (created_at)
            ) {$charset_collate}"
			);

		return
			$result !== false &&
			$this->table_exists(
				$table
			);
	}

	private function add_column_if_missing(
		string $table,
		string $column,
		string $definition
	): bool {
		global $wpdb;

		if (
			$this->column_exists(
				$table,
				$column
			)
		) {
			return true;
		}

		$result =
			$wpdb->query(
				"ALTER TABLE {$table}
            ADD COLUMN {$column}
            {$definition}"
			);

		return $result !== false;
	}

	private function add_index_if_missing(
		string $table,
		string $index,
		string $column
	): bool {
		global $wpdb;

		if (
			$this->index_exists(
				$table,
				$index
			)
		) {
			return true;
		}

		$result =
			$wpdb->query(
				"ALTER TABLE {$table}
            ADD KEY {$index} ({$column})"
			);

		return $result !== false;
	}

	private function column_exists(
		string $table,
		string $column
	): bool {
		global $wpdb;

		return (bool)
		$wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS
                FROM {$table}
                LIKE %s",
				$column
			)
		);
	}

	private function index_exists(
		string $table,
		string $index
	): bool {
		global $wpdb;

		return (bool)
		$wpdb->get_var(
			$wpdb->prepare(
				"SHOW INDEX
                FROM {$table}
                WHERE Key_name = %s",
				$index
			)
		);
	}

	private function table_exists(
		string $table
	): bool {
		global $wpdb;

		$found =
			$wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$wpdb->esc_like(
						$table
					)
				)
			);

		return $found === $table;
	}

	private function ensure_lead_status_column(
		string $table
	): bool {
		global $wpdb;

		$column =
			$wpdb->get_row(
				"SHOW COLUMNS
            FROM {$table}
            LIKE 'status'"
			);

		if (!$column) {
			return false;
		}

		$type =
			strtolower(
				(string)
				$column->Type
			);

		$default =
			(string)
			$column->Default;

		if (
			$type === 'varchar(80)' &&
			$default === 'pending'
		) {
			return true;
		}

		$result =
			$wpdb->query(
				"ALTER TABLE {$table}
            MODIFY COLUMN status
            VARCHAR(80)
            NOT NULL
            DEFAULT 'pending'"
			);

		return $result !== false;
	}

	private function has_existing_database(): bool
	{
		global $wpdb;

		return (
			$this->table_exists(
				$wpdb->prefix .
					'bcob_forms'
			) ||
			$this->table_exists(
				$wpdb->prefix .
					'bcob_leads'
			) ||
			$this->table_exists(
				$wpdb->prefix .
					'bcob_payments'
			)
		);
	}

	private function acquire_upgrade_lock(): bool
	{
		$now =
			time();

		if (
			add_option(
				self::UPGRADE_LOCK_OPTION,
				$now,
				'',
				false
			)
		) {
			return true;
		}

		$locked_at =
			(int) get_option(
				self::UPGRADE_LOCK_OPTION,
				0
			);

		/*
     * Another request is currently upgrading.
     */
		if (
			$locked_at > 0 &&
			($now - $locked_at) < 300
		) {
			return false;
		}

		/*
     * Remove stale lock.
     */
		delete_option(
			self::UPGRADE_LOCK_OPTION
		);

		return add_option(
			self::UPGRADE_LOCK_OPTION,
			$now,
			'',
			false
		);
	}

	private function release_upgrade_lock(): void
	{
		delete_option(
			self::UPGRADE_LOCK_OPTION
		);
	}

	private function create_tables(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$forms_table    = $wpdb->prefix . 'bcob_forms';
		$leads_table    = $wpdb->prefix . 'bcob_leads';
		$prefill_table  = $wpdb->prefix . 'bcob_prefill_links';
		$payments_table = $wpdb->prefix . 'bcob_payments';
		$abandonment_table = $wpdb->prefix . 'bcob_abandoned_sessions';
		$magic_links_table = $wpdb->prefix . 'bcob_magic_links';
		$membership_counters_table = $wpdb->prefix . 'bcob_membership_counters';
		$logs_table = $wpdb->prefix . 'bcob_logs';

		$sql = [];

		$sql[] = "CREATE TABLE {$forms_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			slug VARCHAR(255) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			form_schema LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$leads_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			token VARCHAR(64) NOT NULL,
			submission LONGTEXT NOT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			user_agent TEXT DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token (token),
			KEY form_id (form_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefill_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			token VARCHAR(64) NOT NULL,
			prefill_data LONGTEXT NOT NULL,
			expires_at DATETIME DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			claimed_at DATETIME DEFAULT NULL,
			used_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token (token),
			KEY form_id (form_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$payments_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL,
			form_id BIGINT UNSIGNED NOT NULL,
			membership_id BIGINT UNSIGNED DEFAULT NULL,
			portal_index INT UNSIGNED DEFAULT NULL,
			price_snapshot LONGTEXT DEFAULT NULL,
			provider VARCHAR(50) NOT NULL,
			transaction_id VARCHAR(255) NOT NULL,
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(10) NOT NULL DEFAULT 'USD',
			status VARCHAR(30) NOT NULL,
			response LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY lead_id (lead_id),
			KEY form_id (form_id),
			KEY membership_id (membership_id),
			KEY transaction_id (transaction_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$abandonment_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			email VARCHAR(255) NOT NULL,
			token VARCHAR(64) NOT NULL,
			form_data LONGTEXT NOT NULL,
			current_step INT UNSIGNED NOT NULL DEFAULT 1,
			page_url TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			reminder_1_sent_at DATETIME DEFAULT NULL,
			reminder_2_sent_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token (token),
			KEY form_id (form_id),
			KEY email (email),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$magic_links_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL,
			token_hash CHAR(64) NOT NULL,
			expires_at DATETIME NOT NULL,
			is_used TINYINT(1) NOT NULL DEFAULT 0,
			used_at DATETIME DEFAULT NULL,
			signature_ip VARCHAR(45) DEFAULT NULL,
			signature_datetime DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_hash (token_hash),
			KEY lead_id (lead_id),
			KEY is_used (is_used),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$membership_counters_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			year SMALLINT UNSIGNED NOT NULL,
			next_serial BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY year (year)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$logs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(20) NOT NULL,
			channel VARCHAR(50) NOT NULL,
			message TEXT NOT NULL,
			operation VARCHAR(100) DEFAULT NULL,
			operation_id CHAR(36) NOT NULL,
			form_id BIGINT UNSIGNED DEFAULT NULL,
			lead_id BIGINT UNSIGNED DEFAULT NULL,
			payment_id BIGINT UNSIGNED DEFAULT NULL,
			context LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY level (level),
			KEY channel (channel),
			KEY operation (operation),
			KEY operation_id (operation_id),
			KEY form_id (form_id),
			KEY lead_id (lead_id),
			KEY payment_id (payment_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		foreach ($sql as $query) {
			dbDelta($query);
		}
	}
}
