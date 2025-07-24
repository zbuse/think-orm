<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\db\connector\Mongo;
use think\db\builder\Mongo as MongoBuilder;
use think\db\Mongo as MongoQuery;

class MongoConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        // Skip all tests if MongoDB extension is not available
        if (!extension_loaded('mongodb')) {
            $this->markTestSkipped('MongoDB extension is not available');
        }
    }

    public function testMongoConnectorConfiguration(): void
    {
        // Skip if MongoDB classes are not available
        if (!class_exists('\MongoDB\Driver\Manager')) {
            $this->markTestSkipped('MongoDB Driver classes are not available');
        }

        $config = [
            'type' => 'mongo',
            'hostname' => 'localhost',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
            'hostport' => 27017,
            'charset' => 'utf8',
            'pk' => '_id',
            'pk_type' => 'ObjectID',
            'prefix' => '',
            'deploy' => 0,
            'rw_separate' => false,
            'master_num' => 1,
            'fields_strict' => true,
            'fields_cache' => false,
            'trigger_sql' => true,
            'auto_timestamp' => false,
            'datetime_format' => 'Y-m-d H:i:s',
            'pk_convert_id' => false,
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Test basic configuration
        $this->assertEquals('test_db', $mongo->db());
        $this->assertEquals(MongoQuery::class, $mongo->getQueryClass());
        $this->assertEquals(MongoBuilder::class, $mongo->getBuilderClass());
    }

    public function testMongoConnectorConfigurationWithPkConvert(): void
    {
        $config = [
            'database' => 'test_db',
            'pk' => '_id',
            'pk_convert_id' => true,
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // When pk_convert_id is true and pk is '_id', it should be converted to 'id'
        $this->assertEquals('test_db', $mongo->db());
    }

    public function testMongoConnectorDatabaseOperations(): void
    {
        $config = [
            'database' => 'initial_db',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Test database getter
        $this->assertEquals('initial_db', $mongo->db());
        
        // Test database setter
        $mongo->db('new_db');
        $this->assertEquals('new_db', $mongo->db());
    }

    public function testMongoConnectorDsnGeneration(): void
    {
        // This test doesn't require actual MongoDB connection
        $config = [
            'hostname' => 'localhost',
            'database' => 'test_db',
            'username' => 'user',
            'password' => 'pass',
            'hostport' => 27017,
            'dsn' => '', // Empty DSN to trigger automatic generation
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // We can't easily test the DSN generation without accessing private methods
        // But we can verify the connector initializes properly
        $this->assertInstanceOf(Mongo::class, $mongo);
    }

    public function testMongoConnectorWithCustomDsn(): void
    {
        $config = [
            'dsn' => 'mongodb://custom:pass@example.com:27017/custom_db',
            'database' => 'test_db',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Should use the provided DSN instead of generating one
        $this->assertEquals('test_db', $mongo->db());
    }

    public function testMongoConnectorReplicaSetConfiguration(): void
    {
        $config = [
            'hostname' => 'mongo1.example.com,mongo2.example.com,mongo3.example.com',
            'hostport' => '27017,27017,27017',
            'database' => 'replica_db',
            'is_replica_set' => true,
            'username' => 'replica_user',
            'password' => 'replica_pass',
            'deploy' => 1,
            'rw_separate' => true,
            'master_num' => 1,
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        $this->assertEquals('replica_db', $mongo->db());
    }

    public function testMongoConnectorGetTableFields(): void
    {
        $config = [
            'database' => 'test_db',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // MongoDB doesn't have fixed schemas, so getTableFields should return empty array
        $fields = $mongo->getTableFields('test_collection');
        $this->assertIsArray($fields);
        $this->assertEmpty($fields);
    }

    public function testMongoConnectorClose(): void
    {
        $config = [
            'database' => 'test_db',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Test close method doesn't throw errors
        $mongo->close();
        
        // After close, getMongo should return null
        $this->assertNull($mongo->getMongo());
    }

    public function testMongoConnectorBuilder(): void
    {
        $config = [
            'database' => 'test_db',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Test builder is null initially (not initialized until connection)
        $this->assertNull($mongo->getBuilder());
    }

    public function testMongoConnectorCustomBuilder(): void
    {
        $config = [
            'database' => 'test_db',
            'builder' => 'CustomMongoBuilder',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Should return custom builder class name
        $this->assertEquals('CustomMongoBuilder', $mongo->getBuilderClass());
    }

    public function testMongoConnectorCustomQuery(): void
    {
        $config = [
            'database' => 'test_db',
            'query' => 'CustomMongoQuery',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Should return custom query class name
        $this->assertEquals('CustomMongoQuery', $mongo->getQueryClass());
    }

    public function testMongoConnectorTypeMapConfiguration(): void
    {
        $customTypeMap = ['root' => 'object', 'document' => 'stdClass'];
        $config = [
            'database' => 'test_db',
            'type_map' => $customTypeMap,
        ];

        $mongo = new Mongo($config);
        
        // Verify the connector initializes with custom type map
        $this->assertEquals('test_db', $mongo->db());
    }

    public function testMongoConnectorDistributedConfiguration(): void
    {
        $config = [
            'hostname' => ['master.example.com', 'slave1.example.com', 'slave2.example.com'],
            'hostport' => [27017, 27017, 27017],
            'database' => ['master_db', 'slave_db', 'slave_db'],
            'username' => ['master_user', 'slave_user', 'slave_user'],
            'password' => ['master_pass', 'slave_pass', 'slave_pass'],
            'deploy' => 1,
            'rw_separate' => true,
            'master_num' => 1,
            'slave_no' => 1,
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Test that distributed configuration is accepted
        $this->assertInstanceOf(Mongo::class, $mongo);
    }

    public function testMongoConnectorDefaultConfiguration(): void
    {
        // Test with minimal configuration
        $config = [
            'database' => 'minimal_db',
        ];

        $mongo = new Mongo($config);
        
        $this->assertEquals('minimal_db', $mongo->db());
        $this->assertEquals(MongoQuery::class, $mongo->getQueryClass());
        $this->assertEquals(MongoBuilder::class, $mongo->getBuilderClass());
    }

    public function testMongoConnectorGetLastSql(): void
    {
        $config = [
            'database' => 'test_db',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Initially, getLastSql should return empty string
        $this->assertEquals('', $mongo->getLastSql());
    }

    public function testMongoConnectorSessionManagement(): void
    {
        $config = [
            'database' => 'test_db',
            'type_map' => ['root' => 'array', 'document' => 'array'],
        ];

        $mongo = new Mongo($config);
        
        // Initially, no session should exist
        $this->assertNull($mongo->getSession());
    }
}