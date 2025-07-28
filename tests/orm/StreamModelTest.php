<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\Model;
use think\facade\Db;

/**
 * 测试User模型
 */
class StreamTestUser extends Model
{
    protected $table = 'stream_test_user';
    
    public function profile()
    {
        return $this->hasOne(StreamTestProfile::class, 'user_id');
    }
    
    public function articles()
    {
        return $this->hasMany(StreamTestArticle::class, 'user_id');
    }
}

/**
 * 测试Profile模型
 */
class StreamTestProfile extends Model
{
    protected $table = 'stream_test_profile';
}

/**
 * 测试Article模型
 */
class StreamTestArticle extends Model
{
    protected $table = 'stream_test_article';
}

class StreamModelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        try {
            // 创建用户表
            Db::execute('DROP TABLE IF EXISTS `stream_test_user`;');
            Db::execute("CREATE TABLE `stream_test_user` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL DEFAULT '',
              `email` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 创建资料表
            Db::execute('DROP TABLE IF EXISTS `stream_test_profile`;');
            Db::execute("CREATE TABLE `stream_test_profile` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `bio` text,
              PRIMARY KEY (`id`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 创建文章表
            Db::execute('DROP TABLE IF EXISTS `stream_test_article`;');
            Db::execute("CREATE TABLE `stream_test_article` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `title` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 插入测试数据
            for ($i = 1; $i <= 50; $i++) {
                $userId = Db::table('stream_test_user')->insertGetId([
                    'name' => 'User ' . $i,
                    'email' => 'user' . $i . '@example.com'
                ]);
                
                Db::table('stream_test_profile')->insert([
                    'user_id' => $userId,
                    'bio' => 'Bio for user ' . $i
                ]);
                
                // 每个用户2-3篇文章
                for ($j = 1; $j <= rand(2, 3); $j++) {
                    Db::table('stream_test_article')->insert([
                        'user_id' => $userId,
                        'title' => 'Article ' . $j . ' by User ' . $i
                    ]);
                }
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        try {
            Db::execute('DROP TABLE IF EXISTS `stream_test_user`;');
            Db::execute('DROP TABLE IF EXISTS `stream_test_profile`;');
            Db::execute('DROP TABLE IF EXISTS `stream_test_article`;');
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    /**
     * 测试模型的stream方法
     */
    public function testModelStream()
    {
        $count = 0;
        $result = StreamTestUser::where('id', '>', 0)
            ->limit(10)
            ->stream(function($user) use (&$count) {
                $this->assertInstanceOf(StreamTestUser::class, $user);
                $this->assertIsString($user->name);
                $this->assertIsString($user->email);
                $count++;
            });
        
        $this->assertEquals(10, $result);
        $this->assertEquals(10, $count);
    }
    
    /**
     * 测试模型的cursor方法
     */
    public function testModelCursor()
    {
        $count = 0;
        foreach (StreamTestUser::cursor() as $user) {
            $this->assertInstanceOf(StreamTestUser::class, $user);
            $count++;
            if ($count >= 10) break;
        }
        
        $this->assertEquals(10, $count);
    }
    
    /**
     * 测试关联预载入与stream的结合
     */
    public function testStreamWithWith()
    {
        $count = 0;
        $hasProfile = true;
        
        StreamTestUser::with(['profile'])
            ->limit(5)
            ->stream(function($user) use (&$count, &$hasProfile) {
                $this->assertInstanceOf(StreamTestUser::class, $user);
                // 访问关联数据
                if ($user->profile === null) {
                    $hasProfile = false;
                } else {
                    $this->assertInstanceOf(StreamTestProfile::class, $user->profile);
                }
                $count++;
            });
        
        $this->assertEquals(5, $count);
        $this->assertTrue($hasProfile, 'All users should have profiles loaded');
    }
    
    /**
     * 测试延迟加载与stream的结合
     */
    public function testStreamWithLazyLoad()
    {
        $count = 0;
        StreamTestUser::limit(5)
            ->stream(function($user) use (&$count) {
                // 延迟加载 - 每次访问时才查询
                $profile = $user->profile;
                $this->assertInstanceOf(StreamTestProfile::class, $profile);
                $count++;
            });
        
        $this->assertEquals(5, $count);
    }
}