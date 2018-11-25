<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

	/**
	* VESTA CP RESTFULL API CLASS
	* CREATED BY PABLO LUACES PRESAS
	* DOCS: https://vestacp.com/docs/api/
	*
	*	######################### ERROR CODES #########################
	*	0	OK				Command has been successfuly performed
	*	1	E_ARGS			Not enough arguments provided
	*	2	E_INVALID		Object or argument is not valid
	*	3	E_NOTEXIST		Object doesn't exist
	*	4	E_EXISTS		Object already exists
	*	5	E_SUSPENDED		Object is suspended
	*	6	E_UNSUSPENDED	Object is already unsuspended
	*	7	E_INUSE			Object can't be deleted because is used by the other object
	*	8	E_LIMIT			Object cannot be created because of hosting package limits
	*	9	E_PASSWORD		Wrong password
	*	10	E_FORBIDEN		Object cannot be accessed be the user
	*	11	E_DISABLED		Subsystem is disabled
	*	12	E_PARSING		Configuration is broken
	*	13	E_DISK			Not enough disk space to complete the action
	*	14	E_LA			Server is to busy to complete the action
	*	15	E_CONNECT		Connection failed. Host is unreachable
	*	16	E_FTP			FTP server is not responding
	*	17	E_DB			Database server is not responding
	*	18	E_RRD			RRDtool failed to update the database
	*	19	E_UPDATE		Update operation failed
	*	20	E_RESTART		Service restart failed
	**/

	class VestaCP {

		private $config;

		function __construct() {
			$this->config = new stdClass();

			$this->config->user = ''; // Your Vesta CP Panel user
			$this->config->pass = ''; // Your Vesta CP Panel user password
			$this->config->url  = 'https://myserver.com:8083/api/'; // API URL
			$this->config->returnCode  = 'yes'; // yes to return codes, no to return strings
			$this->config->listFormat  = 'json'; // shell, raw, plain, csv, json
		}

		/********************************************************/
		/*					 PRIVATE METHODS					*/
		/********************************************************/

		/*
		* Generic errors output format
		*/
		private function _error($string){
			log_message('error', '[VestaCP] ' . $string);
		}

		/*
		* Standard PHP CURL request method
		*/
		private function _curlRequest($url, $method = 'GET', array $post = array(), array $options = array()) {

			$sslRequired = (substr($url, 4, 1) == 's')? 2:0;

		    $curlConfig = array(
		    	CURLOPT_URL => $url,
		        CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_SSL_VERIFYPEER => 0, // Verify the authenticity of the peer's certificate
		    	CURLOPT_SSL_VERIFYHOST => $sslRequired,
		        CURLOPT_TIMEOUT => 15,
		        CURLOPT_VERBOSE => true
		    );

		    if ($method === 'POST'){ // Is a POST request
		    	$curlConfig[CURLOPT_POST] = 1;
			    $curlConfig[CURLOPT_POSTFIELDS] = http_build_query($post);
		    }else{ // Is a DELETE or UPDATE request
		    	$curlConfig[CURLOPT_CUSTOMREQUEST] = $method;
		    }

			if (!function_exists('curl_version')){ // CURL not available
				$this->_error('CURL extension not available');
			}else{
				if ($sslRequired && (!curl_version()['features'] & CURL_VERSION_SSL)) { // CURL SSL not available
					$this->_error('SSL is required, but not supported with this CURL installation');
				}
			}

		    $curlConexion = curl_init();

			if (!$curlConexion){
				$error = $this->_error('CURL ERROR: Can\'t connect with specified server!');
				$this->_error($error);
				show_error($error);
			}

		    curl_setopt_array($curlConexion, ($curlConfig + $options));
		    $result = curl_exec($curlConexion);
		    curl_close($curlConexion);

			if (empty($result)) $this->_error('CURL ERROR: Not response from server!');

		    return $result;
		}

		/*
		* API Generic request method
		*/
		private function _request($action = '', $data = array(), $method = 'POST'){

			if (empty($this->config->user) || empty($this->config->pass)) show_error('Set your USER & PASS keywords first!');
			else if (empty($this->config->url)) show_error('Set your server URL first!');

			$configData = array(
			    'user' => $this->config->user,
			    'password' => $this->config->pass,
			    'cmd' => $action
			);

			return $this->_curlRequest($this->config->url, $method, ($configData + $data));
		}

		/********************************************************/
		/*					 PUBLIC METHODS						*/
		/********************************************************/

		public function checkUser($username, $password){
			return $this->_request('v-check-user-password', array(
				'returncode' => $this->config->returnCode,
				'arg1' => $username,
			    'arg2' => $password
			));
		}

		public function createUserAccount($username, $password, $email, $package, $fist_name, $last_name){
			return $this->_request('v-add-user', array(
				'returncode' => $this->config->returnCode,
				'arg1' => $username,
			    'arg2' => $password,
			    'arg3' => $email,
			    'arg4' => $package,
			    'arg5' => $fist_name,
			    'arg6' => $last_name
			));
		}

		public function deleteUserAccount($username){
			return $this->_request('v-delete-user', array(
				'returncode' => $this->config->returnCode,
				'arg1' => $username
			));
		}

		/*
		* 	listUser() Response example (JSON)
		*
		*	Array(
		*	    [FNAME] => Jhon
		*	    [LNAME] => Ferrer
		*	    [PACKAGE] => default
		*	    [WEB_TEMPLATE] => default
		*	    [BACKEND_TEMPLATE] =>
		*	    [PROXY_TEMPLATE] => hosting
		*	    [DNS_TEMPLATE] => default
		*	    [WEB_DOMAINS] => 100
		*	    ...
		*	) or NULL
		*/
		public function listUser($username){
			$answer = $this->_request('v-list-user', array(
				'arg1' => $username,
    			'arg2' => $this->config->listFormat
			));

			if ($this->config->listFormat == 'json'){
				$data = json_decode($answer, true);
				return (!is_null($data))? $data[$username]:NULL;
			}else return $answer;
		}

		/**
		*	######################### COMPLETE ACTIONS LIST #########################
		*
		*	v-acknowledge-user-notification  v-change-remote-dns-domain-ttl    v-delete-sys-quota               v-list-sys-memory-status       v-suspend-database-host
		*	v-activate-vesta-license         v-change-sys-config-value         v-delete-sys-sftp-jail           v-list-sys-mysql-config        v-suspend-databases
		*	v-add-backup-host                v-change-sys-hostname             v-delete-user                    v-list-sys-network-status      v-suspend-dns-domain
		*	v-add-cron-job                   v-change-sys-ip-name              v-delete-user-backup             v-list-sys-nginx-config        v-suspend-dns-domains
		*	v-add-cron-letsencrypt-job       v-change-sys-ip-nat               v-delete-user-backup-exclusions  v-list-sys-pgsql-config        v-suspend-dns-record
		*	v-add-cron-reports               v-change-sys-ip-owner             v-delete-user-favourites         v-list-sys-php-config          v-suspend-domain
		*	v-add-cron-restart-job           v-change-sys-ip-status            v-delete-user-ips                v-list-sys-proftpd-config      v-suspend-firewall-rule
		*	v-add-cron-vesta-autoupdate      v-change-sys-language             v-delete-user-notification       v-list-sys-rrd                 v-suspend-mail-account
		*	v-add-database                   v-change-sys-service-config       v-delete-user-package            v-list-sys-services            v-suspend-mail-accounts
		*	v-add-database-host              v-change-sys-timezone             v-delete-user-sftp-jail          v-list-sys-shells              v-suspend-mail-domain
		*	v-add-dns-domain                 v-change-sys-vesta-ssl            v-delete-vesta-softaculous       v-list-sys-spamd-config        v-suspend-mail-domains
		*	v-add-dns-on-web-alias           v-change-user-contact             v-delete-web-domain              v-list-sys-users               v-suspend-remote-dns-host
		*	v-add-dns-record                 v-change-user-language            v-delete-web-domain-alias        v-list-sys-vesta-autoupdate    v-suspend-user
		*	v-add-domain                     v-change-user-name                v-delete-web-domain-backend      v-list-sys-vesta-ssl           v-suspend-web-domain
		*	v-add-firewall-ban               v-change-user-ns                  v-delete-web-domain-ftp          v-list-sys-vesta-updates       v-suspend-web-domains
		*	v-add-firewall-chain             v-change-user-package             v-delete-web-domain-httpauth     v-list-sys-vsftpd-config       v-sync-dns-cluster
		*	v-add-firewall-rule              v-change-user-password            v-delete-web-domain-proxy        v-list-sys-web-status          v-unsuspend-cron-job
		*	v-add-fs-archive                 v-change-user-shell               v-delete-web-domains             v-list-user                    v-unsuspend-cron-jobs
		*	v-add-fs-directory               v-change-user-template            v-delete-web-domain-ssl          v-list-user-backup             v-unsuspend-database
		*	v-add-fs-file                    v-change-web-domain-backend-tpl   v-delete-web-domain-stats        v-list-user-backup-exclusions  v-unsuspend-database-host
		*	v-add-letsencrypt-domain         v-change-web-domain-ftp-password  v-delete-web-domain-stats-user   v-list-user-backups            v-unsuspend-databases
		*	v-add-letsencrypt-user           v-change-web-domain-ftp-path      v-extract-fs-archive             v-list-user-favourites         v-unsuspend-dns-domain
		*	v-add-mail-account               v-change-web-domain-httpauth      v-generate-api-key               v-list-user-ips                v-unsuspend-dns-domains
		*	v-add-mail-account-alias         v-change-web-domain-ip            v-generate-password-hash         v-list-user-log                v-unsuspend-dns-record
		*	v-add-mail-account-autoreply     v-change-web-domain-name          v-generate-ssl-cert              v-list-user-notifications      v-unsuspend-domain
		*	v-add-mail-account-forward       v-change-web-domain-proxy-tpl     v-get-dns-domain-value           v-list-user-ns                 v-unsuspend-firewall-rule
		*	v-add-mail-account-fwd-only      v-change-web-domain-sslcert       v-get-fs-file-type               v-list-user-package            v-unsuspend-mail-account
		*	v-add-mail-domain                v-change-web-domain-sslhome       v-get-mail-account-value         v-list-user-packages           v-unsuspend-mail-accounts
		*	v-add-mail-domain-antispam       v-change-web-domain-stats         v-get-mail-domain-value          v-list-users                   v-unsuspend-mail-domain
		*	v-add-mail-domain-antivirus      v-change-web-domain-tpl           v-get-sys-timezone               v-list-users-stats             v-unsuspend-mail-domains
		*	v-add-mail-domain-catchall       v-check-api-key                   v-get-sys-timezones              v-list-user-stats              v-unsuspend-remote-dns-host
		*	v-add-mail-domain-dkim           v-check-fs-permission             v-get-user-salt                  v-list-web-domain              v-unsuspend-user
		*	v-add-remote-dns-domain          v-check-letsencrypt-domain        v-get-user-value                 v-list-web-domain-accesslog    v-unsuspend-web-domain
		*	v-add-remote-dns-host            v-check-user-hash                 v-get-web-domain-value           v-list-web-domain-errorlog     v-unsuspend-web-domains
		*	v-add-remote-dns-record          v-check-user-password             v-insert-dns-domain              v-list-web-domains             v-update-database-disk
		*	v-add-sys-firewall               v-check-vesta-license             v-insert-dns-record              v-list-web-domain-ssl          v-update-databases-disk
		*	v-add-sys-ip                     v-copy-fs-directory               v-insert-dns-records             v-list-web-stats               v-update-dns-templates
		*	v-add-sys-quota                  v-copy-fs-file                    v-list-backup-host               v-list-web-templates           v-update-firewall
		*	v-add-sys-sftp-jail              v-deactivate-vesta-license        v-list-cron-job                  v-list-web-templates-backend   v-update-host-certificate
		*	v-add-user                       v-delete-backup-host              v-list-cron-jobs                 v-list-web-templates-proxy     v-update-letsencrypt-ssl
		*	v-add-user-favourites            v-delete-cron-job                 v-list-database                  v-move-fs-directory            v-update-mail-domain-disk
		*	v-add-user-notification          v-delete-cron-reports             v-list-database-host             v-move-fs-file                 v-update-mail-domains-disk
		*	v-add-user-package               v-delete-cron-restart-job         v-list-database-hosts            v-open-fs-config               v-update-sys-ip
		*	v-add-user-sftp-jail             v-delete-cron-vesta-autoupdate    v-list-databases                 v-open-fs-file                 v-update-sys-ip-counters
		*	v-add-vesta-softaculous          v-delete-database                 v-list-database-types            v-rebuild-cron-jobs            v-update-sys-queue
		*	v-add-web-domain                 v-delete-database-host            v-list-dns-domain                v-rebuild-databases            v-update-sys-rrd
		*	v-add-web-domain-alias           v-delete-databases                v-list-dns-domains               v-rebuild-dns-domain           v-update-sys-rrd-apache2
		*	v-add-web-domain-backend         v-delete-dns-domain               v-list-dns-records               v-rebuild-dns-domains          v-update-sys-rrd-ftp
		*	v-add-web-domain-ftp             v-delete-dns-domains              v-list-dns-template              v-rebuild-mail-domains         v-update-sys-rrd-httpd
		*	v-add-web-domain-httpauth        v-delete-dns-domains-src          v-list-dns-templates             v-rebuild-user                 v-update-sys-rrd-la
		*	v-add-web-domain-proxy           v-delete-dns-on-web-alias         v-list-firewall                  v-rebuild-web-domains          v-update-sys-rrd-mail
		*	v-add-web-domain-ssl             v-delete-dns-record               v-list-firewall-ban              v-restart-cron                 v-update-sys-rrd-mem
		*	v-add-web-domain-stats           v-delete-domain                   v-list-firewall-rule             v-restart-dns                  v-update-sys-rrd-mysql
		*	v-add-web-domain-stats-user      v-delete-firewall-ban             v-list-fs-directory              v-restart-ftp                  v-update-sys-rrd-net
		*	v-backup-user                    v-delete-firewall-chain           v-list-letsencrypt-user          v-restart-mail                 v-update-sys-rrd-nginx
		*	v-backup-users                   v-delete-firewall-rule            v-list-mail-account              v-restart-proxy                v-update-sys-rrd-pgsql
		*	v-change-cron-job                v-delete-fs-directory             v-list-mail-account-autoreply    v-restart-service              v-update-sys-rrd-ssh
		*	v-change-database-host-password  v-delete-fs-file                  v-list-mail-accounts             v-restart-system               v-update-sys-vesta
		*	v-change-database-owner          v-delete-letsencrypt-domain       v-list-mail-domain               v-restart-web                  v-update-sys-vesta-all
		*	v-change-database-password       v-delete-mail-account             v-list-mail-domain-dkim          v-restart-web-backend          v-update-user-backup-exclusions
		*	v-change-database-user           v-delete-mail-account-alias       v-list-mail-domain-dkim-dns      v-restore-user                 v-update-user-counters
		*	v-change-dns-domain-exp          v-delete-mail-account-autoreply   v-list-mail-domains              v-schedule-letsencrypt-domain  v-update-user-disk
		*	v-change-dns-domain-ip           v-delete-mail-account-forward     v-list-remote-dns-hosts          v-schedule-user-backup         v-update-user-package
		*	v-change-dns-domain-soa          v-delete-mail-account-fwd-only    v-list-sys-clamd-config          v-schedule-user-restore        v-update-user-quota
		*	v-change-dns-domain-tpl          v-delete-mail-domain              v-list-sys-config                v-schedule-vesta-softaculous   v-update-user-stats
		*	v-change-dns-domain-ttl          v-delete-mail-domain-antispam     v-list-sys-cpu-status            v-search-domain-owner          v-update-web-domain-disk
		*	v-change-dns-record              v-delete-mail-domain-antivirus    v-list-sys-db-status             v-search-fs-object             v-update-web-domains-disk
		*	v-change-dns-record-id           v-delete-mail-domain-catchall     v-list-sys-disk-status           v-search-object                v-update-web-domain-ssl
		*	v-change-domain-owner            v-delete-mail-domain-dkim         v-list-sys-dns-status            v-search-user-object           v-update-web-domains-stat
		*	v-change-firewall-rule           v-delete-mail-domains             v-list-sys-dovecot-config        v-sign-letsencrypt-csr         v-update-web-domain-stat
		*	v-change-fs-file-permission      v-delete-remote-dns-domain        v-list-sys-info                  v-start-service                v-update-web-domains-traff
		*	v-change-mail-account-password   v-delete-remote-dns-domains       v-list-sys-interfaces            v-stop-firewall                v-update-web-domain-traff
		*	v-change-mail-account-quota      v-delete-remote-dns-host          v-list-sys-ip                    v-stop-service                 v-update-web-templates
		*	v-change-mail-domain-catchall    v-delete-remote-dns-record        v-list-sys-ips                   v-suspend-cron-job
		*	v-change-remote-dns-domain-exp   v-delete-sys-firewall             v-list-sys-languages             v-suspend-cron-jobs
		*	v-change-remote-dns-domain-soa   v-delete-sys-ip                   v-list-sys-mail-status           v-suspend-database
		**/

	} // VestaCP Class End
