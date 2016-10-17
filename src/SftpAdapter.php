<?php

namespace League\Flysystem\Sftp;

use InvalidArgumentException;
use League\Flysystem\Adapter\AbstractFtpAdapter;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use LogicException;
use phpseclib\Net\SFTP;
use phpseclib\Crypt\RSA;
use phpseclib\System\SSH\Agent;
use RuntimeException;

class SftpAdapter extends AbstractFtpAdapter
{
    use StreamedCopyTrait;

    /**
     * @var int
     */
    protected $port = 22;

    /**
     * @var string
     */
    protected $hostFingerprint;

    /**
     * @var string
     */
    protected $privatekey;

    /**
     * @var bool
     */
    protected $useAgent = false;

    /**
     * @var Agent
     */
    private $agent;

    /**
     * @var array
     */
    protected $configurable = ['host', 'hostFingerprint', 'port', 'username', 'password', 'useAgent', 'agent', 'timeout', 'root', 'privateKey', 'permPrivate', 'permPublic', 'directoryPerm', 'NetSftpConnection'];

    /**
     * @var array
     */
    protected $statMap = ['mtime' => 'timestamp', 'size' => 'size'];

    /**
     * @var int
     */
    protected $directoryPerm = 0744;

    /**
     * Prefix a path.
     *
     * @param string $path
     *
     * @return string
     */
    protected function prefix($path)
    {
        return $this->root.ltrim($path, $this->separator);
    }

    /**
     * Set the finger print of the public key of the host you are connecting to.
     *
     * If the key does not match the server identification, the connection will
     * be aborted.
     *
     * @param string $fingerprint Example: '88:76:75:96:c1:26:7c:dd:9f:87:50:db:ac:c4:a8:7c'.
     *
     * @return $this
     */
    public function setHostFingerprint($fingerprint)
    {
        $this->hostFingerprint = $fingerprint;

        return $this;
    }

    /**
     * Set the private key (string or path to local file).
     *
     * @param string $key
     *
     * @return $this
     */
    public function setPrivateKey($key)
    {
        $this->privatekey = $key;

        return $this;
    }

    /**
     * @param boolean $useAgent
     *
     * @return $this
     */
    public function setUseAgent($useAgent)
    {
        $this->useAgent = (bool) $useAgent;

        return $this;
    }

    /**
     * @param Agent $agent
     *
     * @return $this
     */
    public function setAgent(Agent $agent)
    {
        $this->agent = $agent;

        return $this;
    }

    /**
     * Set permissions for new directory
     *
     * @param int $directoryPerm
     *
     * @return $this
     */
    public function setDirectoryPerm($directoryPerm)
    {
        $this->directoryPerm = $directoryPerm;

        return $this;
    }

    /**
     * Get permissions for new directory
     *
     * @return int
     */
    public function getDirectoryPerm()
    {
        return $this->directoryPerm;
    }

    /**
     * Inject the SFTP instance.
     *
     * @param SFTP $connection
     *
     * @return $this
     */
    public function setNetSftpConnection(SFTP $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Connect.
     */
    public function connect()
    {
        $this->connection = $this->connection ?: new SFTP($this->host, $this->port, $this->timeout);
        $this->login();
        $this->setConnectionRoot();
    }

    /**
     * Login.
     *
     * @throws LogicException
     */
    protected function login()
    {
        if ($this->hostFingerprint) {
            $actualFingerprint = $this->getHexFingerprintFromSshPublicKey($this->connection->getServerPublicHostKey());

            if (0 !== strcasecmp($this->hostFingerprint, $actualFingerprint)) {
                throw new LogicException('The authenticity of host '.$this->host.' can\'t be established.');
            }
        }

        $authentication = $this->getAuthentication();

        if (! $this->connection->login($this->getUsername(), $authentication)) {
            throw new LogicException('Could not login with username: '.$this->getUsername().', host: '.$this->host);
        }

        if ($authentication instanceof Agent) {
            $authentication->startSSHForwarding($this->connection);
        }
    }

    /**
     * Convert the SSH RSA public key into a hex formatted fingerprint.
     *
     * @param string $publickey
     * @return string Hex formatted fingerprint, e.g. '88:76:75:96:c1:26:7c:dd:9f:87:50:db:ac:c4:a8:7c'.
     */
    private function getHexFingerprintFromSshPublicKey ($publickey)
    {
        $content = explode(' ', $publickey, 3);
        return implode(':', str_split(md5(base64_decode($content[1])), 2));
    }

    /**
     * Set the connection root.
     */
    protected function setConnectionRoot()
    {
        $root = $this->getRoot();

        if (! $root) {
            return;
        }

        if (! $this->connection->chdir($root)) {
            throw new RuntimeException('Root is invalid or does not exist: '.$root);
        }
        $this->root = $this->connection->pwd() . $this->separator;
    }

    /**
     * Get the password, either the private key or a plain text password.
     *
     * @return Agent|RSA|string
     */
    public function getAuthentication()
    {
        if ($this->useAgent) {
            return $this->getAgent();
        }

        if ($this->privatekey) {
            return $this->getPrivateKey();
        }

        return $this->getPassword();
    }

    /**
     * Get the private get with the password or private key contents.
     *
     * @return RSA
     */
    public function getPrivateKey()
    {
        if (@is_file($this->privatekey)) {
            $this->privatekey = file_get_contents($this->privatekey);
        }

        $key = new RSA();

        if ($password = $this->getPassword()) {
            $key->setPassword($password);
        }

        $key->loadKey($this->privatekey);

        return $key;
    }

    /**
     * @return Agent|bool
     */
    public function getAgent()
    {
        if ( ! $this->agent instanceof Agent) {
            $this->agent = new Agent();
        }

        return $this->agent;
    }

    /**
     * List the contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    protected function listDirectoryContents($directory, $recursive = true)
    {
        $result = [];
        $connection = $this->getConnection();
        $location = $this->prefix($directory);
        $listing = $connection->rawlist($location);

        if ($listing === false) {
            return [];
        }

        foreach ($listing as $filename => $object) {
            if (in_array($filename, ['.', '..'])) {
                continue;
            }

            $path = empty($directory) ? $filename : ($directory.'/'.$filename);
            $result[] = $this->normalizeListingObject($path, $object);

            if ($recursive && $object['type'] === NET_SFTP_TYPE_DIRECTORY) {
                $result = array_merge($result, $this->listDirectoryContents($path));
            }
        }

        return $result;
    }

    /**
     * Normalize a listing response.
     *
     * @param string $path
     * @param array  $object
     *
     * @return array
     */
    protected function normalizeListingObject($path, array $object)
    {
        $permissions = $this->normalizePermissions($object['permissions']);
        $type = ($object['type'] === 1) ? 'file' : 'dir' ;
        $timestamp = $object['mtime'];

        if ($type === 'dir') {
            return compact('path', 'timestamp', 'type');
        }

        $visibility = $permissions & 0044 ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;
        $size = (int) $object['size'];

        return compact('path', 'timestamp', 'type', 'visibility', 'size');
    }

    /**
     * Disconnect.
     */
    public function disconnect()
    {
        $this->connection = null;
    }

    /**
     * @inheritdoc
     */
    public function write($path, $contents, Config $config)
    {
        if ($this->upload($path, $contents, $config) === false) {
            return false;
        }

        return compact('contents', 'visibility', 'path');
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        if ($this->upload($path, $resource, $config) === false) {
            return false;
        }

        return compact('visibility', 'path');
    }

    /**
     * Upload a file.
     *
     * @param string          $path
     * @param string|resource $contents
     * @param Config          $config
     * @return bool
     */
    public function upload($path, $contents, Config $config)
    {
        $connection = $this->getConnection();
        $this->ensureDirectory(Util::dirname($path));
        $config = Util::ensureConfig($config);

        if (! $connection->put($path, $contents, SFTP::SOURCE_STRING)) {
            return false;
        }

        if ($config && $visibility = $config->get('visibility')) {
            $this->setVisibility($path, $visibility);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        $connection = $this->getConnection();

        if (($contents = $connection->get($path)) === false) {
            return false;
        }

        return compact('contents');
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        $stream = tmpfile();
        $connection = $this->getConnection();

        if ($connection->get($path, $stream) === false) {
            fclose($stream);
            return false;
        }

        rewind($stream);

        return compact('stream');
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $contents, Config $config)
    {
        return $this->writeStream($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        $connection = $this->getConnection();

        return $connection->delete($path);
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        $connection = $this->getConnection();

        return $connection->rename($path, $newpath);
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($dirname)
    {
        $connection = $this->getConnection();

        return $connection->delete($dirname, true);
    }

    /**
     * @inheritdoc
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        $connection = $this->getConnection();
        $info = $connection->stat($path);

        if ($info === false) {
            return false;
        }

        $result = Util::map($info, $this->statMap);
        $result['type'] = $info['type'] === NET_SFTP_TYPE_DIRECTORY ? 'dir' : 'file';
        $result['visibility'] = $info['permissions'] & $this->permPublic ? 'public' : 'private';

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getMimetype($path)
    {
        if (! $data = $this->read($path)) {
            return false;
        }

        $data['mimetype'] = Util::guessMimeType($path, $data['contents']);

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function createDir($dirname, Config $config)
    {
        $connection = $this->getConnection();

        if (! $connection->mkdir($dirname, $this->directoryPerm, true)) {
            return false;
        }

        return ['path' => $dirname];
    }

    /**
     * @inheritdoc
     */
    public function getVisibility($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($path, $visibility)
    {
        $visibility = ucfirst($visibility);

        if (! isset($this->{'perm'.$visibility})) {
            throw new InvalidArgumentException('Unknown visibility: '.$visibility);
        }

        $connection = $this->getConnection();

        return $connection->chmod($this->{'perm'.$visibility}, $path);
    }

    /**
     * @inheritdoc
     */
    public function isConnected()
    {
        if ($this->connection instanceof SFTP && $this->connection->isConnected()) {
            return true;
        }

        return false;
    }
}
