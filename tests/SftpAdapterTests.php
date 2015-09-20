<?php

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Sftp\SftpAdapter as Sftp;
use League\Flysystem\Sftp\SftpAdapter;

class SftpTests extends PHPUnit_Framework_TestCase
{
    public function setup()
    {
        if (! defined('NET_SFTP_TYPE_DIRECTORY')) {
            define('NET_SFTP_TYPE_DIRECTORY', 2);
        }
    }

    public function adapterProvider()
    {
        $adapter = new Sftp(['username' => 'test', 'password' => 'test']);
        $mock = Mockery::mock('Net_SFTP');
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
        $this->assertInstanceOf('Crypt_RSA', $adapter->getPrivateKey());
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testPrivateKeyFileSetGet($filesystem, $adapter, $mock)
    {
        file_put_contents($key = __DIR__.'/some.key', 'key contents');
        $this->assertEquals($adapter, $adapter->setPrivateKey($key));
        $this->assertInstanceOf('Crypt_RSA', $adapter->getPrivateKey());
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
        $adapter->connect();
    }

    /**
     * @dataProvider  adapterProvider
     */
    public function testGetPasswordWithKey($filesystem, $adapter, $mock)
    {
        $key = 'private.key';
        $this->assertEquals($adapter, $adapter->setPrivateKey($key));
        $this->assertInstanceOf('Crypt_RSA', $adapter->getPassword());
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
        $mock = Mockery::mock('Net_SFTP');
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
                        'type'        => 1,
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
}
