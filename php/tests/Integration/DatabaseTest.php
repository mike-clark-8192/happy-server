<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Happy\Storage\Database;
use Happy\Storage\Migration;
use Happy\Storage\Model;
use Happy\Storage\Models\Account;
use Happy\Storage\Models\Session;
use Happy\Storage\Models\Machine;
use Happy\Storage\Models\SessionMessage;
use Happy\Storage\Models\Artifact;
use Happy\Services\Encryption\EncryptionService;

class DatabaseTest extends TestCase
{
    protected static bool $initialized = false;

    protected function setUp(): void
    {
        if (!self::$initialized) {
            // Initialize database with in-memory SQLite
            Database::init([
                'connection' => 'sqlite',
                'database' => ':memory:',
            ]);

            // Set up encryption service
            $encryption = new EncryptionService('test-master-secret-for-testing');
            Model::setEncryptionService($encryption);

            // Run migrations
            Migration::run();

            self::$initialized = true;
        }
    }

    public function testDatabaseInitialization(): void
    {
        $this->assertNotNull(Database::connection());
        $this->assertTrue(Database::schema()->hasTable('accounts'));
        $this->assertTrue(Database::schema()->hasTable('sessions'));
        $this->assertTrue(Database::schema()->hasTable('machines'));
    }

    public function testCreateAccount(): void
    {
        $account = Account::create([
            'public_key' => 'test-public-key-' . uniqid(),
            'github_username' => 'testuser-' . uniqid(),
        ]);

        $this->assertNotEmpty($account->id);
        $this->assertEquals(0, $account->version);

        // Reload and verify
        $loaded = Account::find($account->id);
        $this->assertNotNull($loaded);
        $this->assertEquals($account->github_username, $loaded->github_username);
    }

    public function testAccountEncryptedFields(): void
    {
        $account = Account::create([
            'public_key' => 'test-key-' . uniqid(),
        ]);

        $profile = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $settings = ['theme' => 'dark', 'notifications' => true];

        $account->setProfile($profile);
        $account->setSettings($settings);
        $account->save();

        // Reload and verify decryption
        $loaded = Account::find($account->id);
        $this->assertEquals($profile, $loaded->getProfile());
        $this->assertEquals($settings, $loaded->getSettings());

        // Verify raw value is encrypted (not plain JSON)
        $rawProfile = $loaded->getRawAttribute('profile_encrypted');
        $this->assertNotEquals(json_encode($profile), $rawProfile);
    }

    public function testCreateSession(): void
    {
        $account = Account::create([
            'public_key' => 'session-test-key-' . uniqid(),
        ]);

        $session = Session::create([
            'account_id' => $account->id,
            'tag' => 'test-session-' . uniqid(),
        ]);

        $this->assertNotEmpty($session->id);
        $this->assertEquals($account->id, $session->account_id);

        // Test relationship
        $this->assertEquals($account->id, $session->account->id);
    }

    public function testSessionEncryptedFields(): void
    {
        $account = Account::create([
            'public_key' => 'session-enc-key-' . uniqid(),
        ]);

        $session = Session::create([
            'account_id' => $account->id,
        ]);

        $metadata = ['title' => 'Test Session', 'model' => 'gpt-4'];
        $state = ['status' => 'active', 'tokens' => 1000];
        $agentState = ['context' => ['key' => 'value']];

        $session->setMetadata($metadata);
        $session->setState($state);
        $session->setAgentState($agentState);
        $session->save();

        // Reload and verify
        $loaded = Session::find($session->id);
        $this->assertEquals($metadata, $loaded->getMetadata());
        $this->assertEquals($state, $loaded->getState());
        $this->assertEquals($agentState, $loaded->getAgentState());
    }

    public function testSessionUniqueTag(): void
    {
        $account = Account::create([
            'public_key' => 'unique-tag-key-' . uniqid(),
        ]);

        $tag = 'unique-tag-' . uniqid();

        Session::create([
            'account_id' => $account->id,
            'tag' => $tag,
        ]);

        // Trying to create another session with same tag should fail
        $this->expectException(\Exception::class);
        Session::create([
            'account_id' => $account->id,
            'tag' => $tag,
        ]);
    }

    public function testCreateMachine(): void
    {
        $account = Account::create([
            'public_key' => 'machine-test-key-' . uniqid(),
        ]);

        $machine = Machine::create([
            'account_id' => $account->id,
            'name' => 'Test Machine',
        ]);

        $this->assertNotEmpty($machine->id);
        $this->assertEquals('Test Machine', $machine->name);

        // Test daemon state
        $daemonState = ['version' => '1.0', 'status' => 'running'];
        $machine->setDaemonState($daemonState);
        $machine->save();

        $loaded = Machine::find($machine->id);
        $this->assertEquals($daemonState, $loaded->getDaemonState());
    }

    public function testMachineOnlineStatus(): void
    {
        $account = Account::create([
            'public_key' => 'online-test-key-' . uniqid(),
        ]);

        $machine = Machine::create([
            'account_id' => $account->id,
            'name' => 'Online Test Machine',
        ]);

        // Initially not online
        $this->assertFalse($machine->isOnline());

        // Touch to set last_alive_at
        $machine->touch();
        $machine->refresh();

        // Now should be online
        $this->assertTrue($machine->isOnline());
    }

    public function testCreateSessionMessage(): void
    {
        $account = Account::create([
            'public_key' => 'message-test-key-' . uniqid(),
        ]);

        $session = Session::create([
            'account_id' => $account->id,
        ]);

        $content = ['role' => 'user', 'content' => 'Hello, world!'];

        $message = SessionMessage::create([
            'session_id' => $session->id,
            'local_id' => 'msg-1',
            'seq' => 1,
        ]);
        $message->setContent($content);
        $message->save();

        // Reload and verify
        $loaded = SessionMessage::find($message->id);
        $this->assertEquals($content, $loaded->getContent());

        // Test relationship
        $messages = $session->messages()->get();
        $this->assertCount(1, $messages);
    }

    public function testCreateArtifact(): void
    {
        $account = Account::create([
            'public_key' => 'artifact-test-key-' . uniqid(),
        ]);

        $session = Session::create([
            'account_id' => $account->id,
        ]);

        $content = ['type' => 'document', 'data' => 'base64content'];
        $metadata = ['filename' => 'test.pdf', 'mimetype' => 'application/pdf'];

        $artifact = Artifact::create([
            'session_id' => $session->id,
            'account_id' => $account->id,
        ]);
        $artifact->setContent($content);
        $artifact->setMetadata($metadata);
        $artifact->save();

        // Reload and verify
        $loaded = Artifact::find($artifact->id);
        $this->assertEquals($content, $loaded->getContent());
        $this->assertEquals($metadata, $loaded->getMetadata());
    }

    public function testSessionCascadeDelete(): void
    {
        $account = Account::create([
            'public_key' => 'cascade-test-key-' . uniqid(),
        ]);

        $session = Session::create([
            'account_id' => $account->id,
        ]);

        // Create messages and artifacts
        $message = SessionMessage::create([
            'session_id' => $session->id,
            'seq' => 1,
        ]);
        $message->setContent(['text' => 'test']);
        $message->save();

        $artifact = Artifact::create([
            'session_id' => $session->id,
            'account_id' => $account->id,
        ]);
        $artifact->setContent(['data' => 'test']);
        $artifact->save();

        $messageId = $message->id;
        $artifactId = $artifact->id;

        // Delete session with relations
        $session->deleteWithRelations();

        // Verify cascade
        $this->assertNull(Session::find($session->id));
        $this->assertNull(SessionMessage::find($messageId));
        $this->assertNull(Artifact::find($artifactId));
    }

    public function testVersionIncrement(): void
    {
        $account = Account::create([
            'public_key' => 'version-test-key-' . uniqid(),
        ]);

        $this->assertEquals(0, $account->version);

        $account->github_username = 'updated-user';
        $account->save();

        $this->assertEquals(1, $account->version);

        $account->github_username = 'updated-again';
        $account->save();

        $this->assertEquals(2, $account->version);
    }

    public function testTransaction(): void
    {
        $result = Database::transaction(function () {
            $account = Account::create([
                'public_key' => 'tx-test-key-' . uniqid(),
            ]);

            return $account->id;
        });

        $this->assertNotEmpty($result);
        $this->assertNotNull(Account::find($result));
    }

    public function testAccountRelationships(): void
    {
        $account = Account::create([
            'public_key' => 'rel-test-key-' . uniqid(),
        ]);

        // Create multiple sessions
        Session::create(['account_id' => $account->id, 'tag' => 'session-1']);
        Session::create(['account_id' => $account->id, 'tag' => 'session-2']);

        // Create machine
        Machine::create(['account_id' => $account->id, 'name' => 'Machine 1']);

        // Test relationships
        $this->assertCount(2, $account->sessions);
        $this->assertCount(1, $account->machines);
    }
}
