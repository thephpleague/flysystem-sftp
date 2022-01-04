<?php

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Sftp\SftpAdapter as Sftp;
use League\Flysystem\Sftp\SftpAdapter;
use phpseclib\System\SSH\Agent;
use PHPUnit\Framework\TestCase;

/**
 * @covers \League\Flysystem\Sftp\SftpAdapter<extended>
 */
class SftpTests extends TestCase
{
    const SSH_RSA = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQD05PZTxeH6GPDyxLNv7UV05jcK+Y9P8kQnpEZRHOurJVSOB4k6JBXLQtgbffuy8bFYh6mZVx40f5Za0I9mCfPel/xnCu4F1cndZBY3Ww/12rmjYOHie7k9B3h1trJ1mDhXHuiRO6vfy81jMJ9dzJyCwOK9aFGEueQ8WuPMRt9/1g3awi1O0+YZ8gTLtjKbUXLT50/GksiWDFA6DwxjLR7jFEcuPUm/WpBIKMcsbxpjKmTNaCeuoKs9TcpTwg5E311nQfk0oficgyHP/x8m6mNH5q/zOMwaRjyC6LYyBXVJgSKsh7YFf+pRyHFGpWTWKnRKXWG13NLiEKb47SydLe77';

    const SSH_RSA_FINGERPRINT = '88:76:75:96:c1:26:7c:dd:9f:87:50:db:ac:c4:a8:7c';

    protected function setup()
    {
        if (! defined('NET_SFTP_TYPE_DIRECTORY')) {
            define('NET_SFTP_TYPE_DIRECTORY', 2);
        }
    }

    public function adapterProvider()
    {
        $adapter = new Sftp(['username' => 'test', 'password' => 'test']);
        $mock = Mockery::mock('phpseclib\Net\SFTP')->makePartial();
        $mock->shouldReceive('__toString')->andReturn('Net_SFTP');
        $mock->shouldReceive('isConnected')->andReturn(true);
        $mock->shouldReceive('disconnect');
        $adapter->setNetSftpConnection($mock);
        $filesystem = new Filesystem($adapter);

        return [
            [$filesystem, $adapter, $mock],
        ];
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testHas($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => NET_SFTP_TYPE_DIRECTORY,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);

        $this->assertTrue($filesystem->has('something'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testHasFail($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn(false);

        $this->assertFalse($filesystem->has('something'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testWrite($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('put')->andReturn(true, false);
        $mock->shouldReceive('stat')->andReturn(false);
        $mock->shouldReceive('chmod')->andReturn(true);
        $this->assertTrue($filesystem->write('something', 'something', ['visibility' => 'public']));
        $this->assertFalse($filesystem->write('something_else.txt', 'else'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testWriteStream($filesystem, $adapter, $mock)
    {
        $stream = tmpfile();
        $mock->shouldReceive('put')->andReturn(true, false);
        $mock->shouldReceive('stat')->andReturn(false);
        $mock->shouldReceive('chmod')->andReturn(true);
        $this->assertTrue($filesystem->writeStream('something', $stream, ['visibility' => 'public']));
        $this->assertFalse($filesystem->writeStream('something_else.txt', $stream));
        fclose($stream);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDelete($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('delete')->andReturn(true, false);
        $mock->shouldReceive('stat')->andReturn([
            'type'        => 1,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $this->assertTrue($filesystem->delete('something'));
        $this->assertFalse($filesystem->delete('something_else.txt'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testUpdate(FilesystemInterface $filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('put')->andReturn(true, false);
        $mock->shouldReceive('stat')->andReturn([
            'type'        => NET_SFTP_TYPE_DIRECTORY,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $this->assertTrue($filesystem->update('something', 'something'));
        $this->assertFalse($filesystem->update('something_else.txt', 'else'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testUpdateStream(FilesystemInterface $filesystem, $adapter, $mock)
    {
        $stream = tmpfile();
        $mock->shouldReceive('put')->andReturn(true, false);
        $mock->shouldReceive('stat')->andReturn([
            'type'        => NET_SFTP_TYPE_DIRECTORY,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $this->assertTrue($filesystem->updateStream('something', $stream));
        $this->assertFalse($filesystem->updateStream('something_else.txt', $stream));
        fclose($stream);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testSetVisibility($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => 1, // file
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $mock->shouldReceive('chmod')->twice()->andReturn(true, false);
        $this->assertTrue($filesystem->setVisibility('something', 'public'));
        $this->assertFalse($filesystem->setVisibility('something', 'public'));
    }

    /**
     * @dataProvider adapterProvider
     * @expectedException InvalidArgumentException
     */
    public function testSetVisibilityInvalid($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => 1, // file
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $mock->shouldReceive('stat')->once()->andReturn(true);
        $filesystem->setVisibility('something', 'invalid');
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testRename($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => NET_SFTP_TYPE_DIRECTORY,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ], false);
        $mock->shouldReceive('rename')->andReturn(true);
        $result = $filesystem->rename('old', 'new');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDeleteDir($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('delete')->with('some/dirname', true)->andReturn(true);
        $result = $filesystem->deleteDir('some/dirname');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testListContents($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('rawlist')->andReturn(false, [
            '.'       => [],
            'dirname' => [
                'type'        => NET_SFTP_TYPE_DIRECTORY,
                'mtime'       => time(),
                'size'        => 20,
                'permissions' => 0777,
            ],
        ], [
            '..'      => [],
            'dirname' => [
                'type'        => 1,
                'mtime'       => time(),
                'size'        => 20,
                'permissions' => 0777,
            ],
        ]);
        $listing = $adapter->listContents('', true);
        $this->assertInternalType('array', $listing);
        $listing = $adapter->listContents('', true);
        $this->assertInternalType('array', $listing);
        $this->assertCount(2, $listing);
    }

    public function methodProvider()
    {
        $resources = $this->adapterProvider();
        list($filesystem, $adapter, $mock) = reset($resources);

        return [
            [$filesystem, $adapter, $mock, 'getMetadata', 'array'],
            [$filesystem, $adapter, $mock, 'getTimestamp', 'integer'],
            [$filesystem, $adapter, $mock, 'getVisibility', 'string'],
            [$filesystem, $adapter, $mock, 'getSize', 'integer'],
        ];
    }

    /**
     * @dataProvider  methodProvider
     */
    public function testMetaMethods($filesystem, $adapter, $mock, $method, $type)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => NET_SFTP_TYPE_DIRECTORY,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $result = $filesystem->{$method}(uniqid().'object.ext');
        $this->assertInternalType($type, $result);
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testGetVisibility($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => NET_SFTP_TYPE_DIRECTORY,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $result = $adapter->getVisibility(uniqid().'object.ext');
        $this->assertInternalType('array', $result);
        $result = $result['visibility'];
        $this->assertInternalType('string', $result);
        $this->assertEquals('public', $result);
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testGetTimestamp($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => NET_SFTP_TYPE_DIRECTORY,
            'mtime'       => $time = time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $result = $adapter->getTimestamp('object.ext');
        $this->assertInternalType('array', $result);
        $result = $result['timestamp'];
        $this->assertInternalType('integer', $result);
        $this->assertEquals($time, $result);
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testCreateDir($filesystem, $adapter, $mock)
    {
        $directoryPerm = 12345;
        $adapter->setDirectoryPerm($directoryPerm);
        $mock->shouldReceive('mkdir')->once()->with('dirname', $directoryPerm, true)->andReturn(true);
        $mock->shouldReceive('mkdir')->once()->with('dirname_fails', $directoryPerm, true)->andReturn(false);
        $this->assertTrue($filesystem->createDir('dirname'));
        $this->assertFalse($filesystem->createDir('dirname_fails'));
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testRead($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => 1,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);
        $mock->shouldReceive('get')->andReturn('file contents', false);
        $result = $filesystem->read('some.file');
        $this->assertInternalType('string', $result);
        $this->assertEquals('file contents', $result);
        $this->assertFalse($filesystem->read('other.file'));
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testReadStream($filesystem, $adapter, $mock)
    {
        $stream = tmpfile();
        fwrite($stream, 'something');
        $mock->shouldReceive('get')->andReturn($stream, false);
        $result = $adapter->readStream('something');
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('stream', $result);
        $this->assertInternalType('resource', $result['stream']);
        $this->assertFalse($adapter->readStream('something'));
        fclose($stream);
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testGetMimetype($filesystem, $adapter, $mock)
    {
        $mock->shouldReceive('stat')->andReturn([
            'type'        => 1,
            'mtime'       => time(),
            'size'        => 20,
            'permissions' => 0777,
        ]);

        $mock->shouldReceive('get')->andReturn('file contents', false);
        $result = $filesystem->getMimetype('some.file');
        $this->assertInternalType('string', $result);
        $this->assertEquals('text/plain', $result);
        $this->assertFalse($filesystem->getMimetype('some.file'));
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testPrivateKeySetGet($filesystem, $adapter, $mock)
    {
        $key = 'private.key';
        $this->assertEquals($adapter, $adapter->setPrivateKey($key));
        $this->assertInstanceOf('phpseclib\Crypt\RSA', $adapter->getPrivateKey());
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testAgentSetGet($filesystem, SftpAdapter $adapter, $mock)
    {
        if (!isset($_SERVER['SSH_AUTH_SOCK'])) {
            $this->markTestSkipped('This test requires an SSH Agent (SSH_AUTH_SOCK env variable).');
        }

        $this->assertEquals($adapter, $adapter->setUseAgent(true));
        $this->assertInstanceOf('phpseclib\System\SSH\Agent', $adapter->getAuthentication());
        $this->assertSame($adapter->getAgent(), $adapter->getAgent());

        $agent = new Agent;
        $adapter->setAgent($agent);
        $this->assertEquals($agent, $agent);
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testPrivateKeyFileSetGet($filesystem, $adapter, $mock)
    {
        file_put_contents($key = __DIR__.'/some.key', 'key contents');
        $this->assertEquals($adapter, $adapter->setPrivateKey($key));
        $this->assertInstanceOf('phpseclib\Crypt\RSA', $adapter->getPrivateKey());
        @unlink($key);
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testDirectoryPermSetGet($filesystem, $adapter, $mock)
    {
        $directoryPerm = 12345;
        $this->assertEquals($adapter, $adapter->setDirectoryPerm($directoryPerm));
        $this->assertEquals($directoryPerm, $adapter->getDirectoryPerm());
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testConnect($filesystem, $adapter, $mock)
    {
        $adapter->setNetSftpConnection($mock);
        $mock->shouldReceive('login')->with('test', 'test')->andReturn(true);
        $mock->shouldReceive('disableStatCache');
        $adapter->connect();
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testConnectWithAgent($filesystem, SftpAdapter $adapter, $mock)
    {
        if (!isset($_SERVER['SSH_AUTH_SOCK'])) {
            $this->markTestSkipped('This test requires an SSH Agent (SSH_AUTH_SOCK env variable).');
        }

        $agent = new Agent;
        $adapter->setUseAgent(true);
        $adapter->setAgent($agent);
        $adapter->setNetSftpConnection($mock);
        $mock->shouldReceive('login')->with('test', $agent)->andReturn(true);
        $adapter->connect();
        $this->assertEquals(Agent::FORWARD_REQUEST, $agent->forward_status);
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testGetPasswordWithKey($filesystem, SftpAdapter $adapter, $mock)
    {
        $key = 'private.key';
        $this->assertEquals($adapter, $adapter->setPrivateKey($key));
        $this->assertInstanceOf('phpseclib\Crypt\RSA', $adapter->getAuthentication());
    }


    /**
     * @dataProvider  adapterProvider
     */
    public function testPassphraseSetGet($filesystem, SftpAdapter $adapter, $mock)
    {
        $passphrase = 'passphrase';
        $this->assertEquals($adapter, $adapter->setPassphrase($passphrase));
        $this->assertEquals($passphrase, $adapter->getPassphrase());
    }


    /**
     * @dataProvider  adapterProvider
     */
    public function testPassphraseFallback($filesystem, SftpAdapter $adapter, $mock)
    {
        $passphrase = 'passphrase';
        $this->assertEquals($adapter, $adapter->setPassword($passphrase));
        $this->assertEquals($passphrase, $adapter->getPassphrase());
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testPassphraseAndPasswordFallback($filesystem, SftpAdapter $adapter, $mock)
    {
        $passphrase = 'passphrase';
        $password = 'password';
        $this->assertEquals($adapter, $adapter->setPassword($password));
        $this->assertEquals($adapter, $adapter->setPassphrase($passphrase));
        $this->assertEquals($passphrase, $adapter->getPassphrase());
        $this->assertEquals($password, $adapter->getPassword());
    }


    /**
     * @dataProvider  adapterProvider
     */
    public function testConnectWithDoubleAuthentication($filesystem, $adapter, $mock)
    {
        $adapter->setPrivateKey('private.key');
        $adapter->setNetSftpConnection($mock);

        $expectedAuths = [$adapter->getPrivateKey(), 'test'];
        $mock->shouldReceive('login')->with('test', Mockery::on(function($auth) use (&$expectedAuths) {
            return $auth == array_shift($expectedAuths);
        }))->twice()->andReturn(false, true);

        $adapter->connect();
    }

    /**
     * @dataProvider  adapterProvider
     * @expectedException LogicException
     */
    public function testLoginFail($filesystem, $adapter, $mock)
    {
        $adapter->setNetSftpConnection($mock);
        $mock->shouldReceive('login')->with('test', 'test')->andReturn(false);
        $adapter->connect();
    }

    /**
     * @dataProvider  adapterProvider
     *
     * @param             $filesystem
     * @param SftpAdapter $adapter
     * @param             $mock
     */
    public function testIsConnected($filesystem, SftpAdapter $adapter, $mock)
    {
        $adapter->setNetSftpConnection($mock);
        $mock->shouldReceive('isConnected')->andReturn(true);
        $this->assertTrue($adapter->isConnected());
    }

    /**
     * @dataProvider  adapterProvider
     *
     * @param             $filesystem
     * @param SftpAdapter $adapter
     */
    public function testIsNotConnected($filesystem, SftpAdapter $adapter)
    {
        $mock = Mockery::mock('phpseclib\Net\SFTP');
        $mock->shouldReceive('__toString')->andReturn('Net_SFTP');
        $mock->shouldReceive('disconnect');
        $mock->shouldReceive('isConnected')->andReturn(false);
        $adapter->setNetSftpConnection($mock);
        $this->assertFalse($adapter->isConnected());
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testConnectWithRoot($filesystem, $adapter, $mock)
    {
        $adapter->setRoot('/root');
        $adapter->setNetSftpConnection($mock);
        $mock->shouldReceive('login')->with('test', 'test')->andReturn(true);
        $mock->shouldReceive('chdir')->with('/root/')->andReturn(true);
        $adapter->connect();
        $adapter->disconnect();
    }

    /**
     * @dataProvider  adapterProvider
     * @expectedException RuntimeException
     */
    public function testConnectWithInvalidRoot($filesystem, $adapter, $mock)
    {
        $adapter->setRoot('/root');
        $adapter->setNetSftpConnection($mock);
        $mock->shouldReceive('login')->with('test', 'test')->andReturn(true);
        $mock->shouldReceive('chdir')->with('/root/')->andReturn(false);
        $adapter->connect();
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testListContentsDir($filesystem, $adapter, $mock)
    {
        $mock
            ->shouldReceive('rawlist')
            ->andReturn(
                [
                    'dirname' =>
                        [
                        'type'        => NET_SFTP_TYPE_DIRECTORY,
                        'mtime'       => time(),
                        'permissions' => 0777,
                        'filename'    => 'dirname'
                        ],
                    'filename' =>
                        [
                        'mtime'       => time(),
                        'size'        => 20,
                        'permissions' => 0777,
                        'filename'    => 'filename'
                        ],
                ]
            );

        $listing = $filesystem->listContents('');
        $this->assertInternalType('array', $listing);
        $this->assertCount(2, $listing);
    }

    public function testNetSftpConnectionSetter()
    {
        $settings = [
            'NetSftpConnection' => $mock = Mockery::mock('phpseclib\Net\SFTP'),
        ];

        $mock->shouldReceive('isConnected')->andReturn(true);
        $mock->shouldReceive('disconnect');

        $adapter = new Sftp($settings);
        $this->assertEquals($mock, $adapter->getConnection());
    }

    public function testHostFingerprintIsVerifiedIfProvided()
    {
        $adapter = new SftpAdapter([
            'host' => 'example.org',
            'username' => 'user',
            'password' => '123456',
            'hostFingerprint' => self::SSH_RSA_FINGERPRINT,
        ]);

        $connection = Mockery::mock('phpseclib\Net\SFTP');
        $connection->shouldReceive('getServerPublicHostKey')
            ->andReturn(self::SSH_RSA);
        $connection->shouldReceive('login')
            ->with('user', '123456')
            ->andReturn(TRUE);
        $connection->shouldReceive('disableStatCache');
        $connection->shouldReceive('disconnect');

        $adapter->setNetSftpConnection($connection);

        $adapter->connect();
    }

    public function testUsingPingForTheConnectivityCheck()
    {
        $adapter = new SftpAdapter([
            'usePingForConnectivityCheck' => true,
            'host' => 'example.org',
            'username' => 'user',
            'password' => '123456',
            'hostFingerprint' => self::SSH_RSA_FINGERPRINT,
        ]);

        $connection = Mockery::mock('phpseclib\Net\SFTP');
        $connection->shouldReceive('getServerPublicHostKey')
            ->andReturn(self::SSH_RSA);
        $connection->shouldReceive('login')
            ->with('user', '123456')
            ->andReturn(TRUE);
        $connection->shouldReceive('disableStatCache');
        $connection->shouldReceive('isConnected')->andReturn(true);
        $connection->shouldReceive('ping')->andReturn(true, false);
        $connection->shouldReceive('disconnect');

        $adapter->setNetSftpConnection($connection);

        $adapter->connect();

        self::assertTrue($adapter->isConnected());
        self::assertFalse($adapter->isConnected());
    }

    public function testHostFingerprintNotIsVerifiedIfNotProvided ()
    {
        $adapter = new SftpAdapter([
            'host' => 'example.org',
            'username' => 'user',
            'password' => '123456',
        ]);

        $connection = Mockery::mock('phpseclib\Net\SFTP');

        $connection->shouldReceive('getServerPublicHostKey')
            ->never();
        $connection->shouldReceive('login')
            ->with('user', '123456')
            ->andReturn(TRUE);
        $connection->shouldReceive('disableStatCache');
        $connection->shouldReceive('disconnect');
        $adapter->setNetSftpConnection($connection);

        $adapter->connect();
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage The authenticity of host example.org can't be established.
     */
    public function testMisMatchingHostFingerprintAbortsLogin ()
    {
        $adapter = new SftpAdapter([
            'host' => 'example.org',
            'username' => 'user',
            'password' => '123456',
            'hostFingerprint' => '00:00:00:00:00:00:00:00:00:00:00:00:00:00:00:00',
        ]);

        $connection = Mockery::mock('phpseclib\Net\SFTP');

        $connection->shouldReceive('getServerPublicHostKey')
            ->andReturn(self::SSH_RSA);

        $connection->shouldReceive('login')
            ->never();

        $connection->shouldReceive('disableStatCache');
        $connection->shouldReceive('disconnect');

        $adapter->setNetSftpConnection($connection);

        $adapter->connect();
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage Could not connect to server to verify public key.
     */
    public function testCantConnectToCheckHostFingerprintAbortsLogin()
    {
        $adapter = new SftpAdapter([
            'host' => 'example.org',
            'username' => 'user',
            'password' => '123456',
            'hostFingerprint' => '00:00:00:00:00:00:00:00:00:00:00:00:00:00:00:00',
        ]);

        $connection = Mockery::mock('phpseclib\Net\SFTP');

        $connection->shouldReceive('getServerPublicHostKey')
            ->andReturn(false); // getServerPublicHostKey returns false if it cant connect.

        $connection->shouldReceive('login')
            ->never();

        $connection->shouldReceive('disableStatCache');
        $connection->shouldReceive('disconnect');

        $adapter->setNetSftpConnection($connection);

        $adapter->connect();
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testListContentsWithZeroNamedDir($filesystem, $adapter, $mock)
    {
        $mock
            ->shouldReceive('rawlist')
            ->andReturn(
                [
                    '0' =>
                        [
                            'type'        => NET_SFTP_TYPE_DIRECTORY,
                            'mtime'       => time(),
                            'permissions' => 0777,
                            'filename'    => '0'
                        ]
                ]
            );

        $listing = $filesystem->listContents('');
        $this->assertInternalType('array', $listing);
        $this->assertCount(1, $listing);
    }
}
