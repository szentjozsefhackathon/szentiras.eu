<?php

namespace SzentirasHu\Test\Editor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ApiKey;
use SzentirasHu\Data\Entity\ContactMessage;
use SzentirasHu\Service\Editor\EditorService;
use SzentirasHu\Test\Common\TestCase;

class ContactMessageEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    private AnonymousId $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(EditorService::class, function ($mock) {
            $mock->shouldReceive('currentIsEditor')->andReturn(true);
        });

        $this->editor = AnonymousId::factory()->create();
        $this->withSession(['anonymous_token' => $this->editor->token]);
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    public function testComposePageShowsRecipientAndApiKeys(): void
    {
        $owner = AnonymousId::factory()->create();
        ApiKey::factory()->selfService()->create([
            'name' => 'Tulajdonos kulcsa',
            'created_by_anonymous_id' => $owner->id,
        ]);

        $response = $this->get(route('editor.contactMessages.compose', $owner));

        $response->assertOk();
        $response->assertSee($owner->token);
        $response->assertSee('Tulajdonos kulcsa');
    }

    public function testEditorCanSendMessageToUser(): void
    {
        $recipient = AnonymousId::factory()->create();

        $response = $this->post(route('editor.contactMessages.send', $recipient), [
            'message' => 'Kérdésünk van az API-használatoddal kapcsolatban.',
        ]);

        $message = ContactMessage::query()->where('receiver_anonymous_id', $recipient->id)->first();

        $this->assertNotNull($message);
        $response->assertRedirect(route('editor.contactMessages.thread', $message->id));
        $this->assertSame($this->editor->id, $message->sender_anonymous_id);
        $this->assertNull($message->parent_id);
        $this->assertFalse($message->is_read);
    }

    public function testSendRequiresMessage(): void
    {
        $recipient = AnonymousId::factory()->create();

        $response = $this->post(route('editor.contactMessages.send', $recipient), [
            'message' => '',
        ]);

        $response->assertSessionHasErrors('message');
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function testSendToUnknownRecipientIsNotFound(): void
    {
        $response = $this->post(route('editor.contactMessages.send', 999999), [
            'message' => 'Hello',
        ]);

        $response->assertNotFound();
    }

    public function testEditorReplyToOwnMessageGoesToOriginalRecipient(): void
    {
        $recipient = AnonymousId::factory()->create();
        $root = ContactMessage::create([
            'sender_anonymous_id' => $this->editor->id,
            'receiver_anonymous_id' => $recipient->id,
            'message' => 'Első üzenet',
        ]);

        $this->post(route('editor.contactMessages.reply', $root->id), [
            'reply' => 'Második üzenet',
        ]);

        $reply = ContactMessage::query()->where('parent_id', $root->id)->first();
        $this->assertNotNull($reply);
        $this->assertSame($recipient->id, $reply->receiver_anonymous_id);
    }

    public function testEditorReplyToUserMessageGoesToSender(): void
    {
        $user = AnonymousId::factory()->create();
        $root = ContactMessage::create([
            'sender_anonymous_id' => $user->id,
            'message' => 'Felhasználói kérdés',
        ]);

        $this->post(route('editor.contactMessages.reply', $root->id), [
            'reply' => 'Szerkesztői válasz',
        ]);

        $reply = ContactMessage::query()->where('parent_id', $root->id)->first();
        $this->assertNotNull($reply);
        $this->assertSame($user->id, $reply->receiver_anonymous_id);
    }

    public function testThreadAndIndexShowEditorInitiatedMessage(): void
    {
        $recipient = AnonymousId::factory()->create();
        $root = ContactMessage::create([
            'sender_anonymous_id' => $this->editor->id,
            'receiver_anonymous_id' => $recipient->id,
            'message' => 'Szerkesztői megkeresés',
        ]);

        $this->get(route('editor.contactMessages.thread', $root->id))
            ->assertOk()
            ->assertSee($recipient->token);

        $this->get(route('editor.contactMessages.index'))
            ->assertOk()
            ->assertSee('Szerkesztői megkeresés');
    }

    public function testHeaderShowsUnreadBadgeLinkingToInbox(): void
    {
        $recipient = AnonymousId::factory()->create();
        ContactMessage::create([
            'sender_anonymous_id' => $this->editor->id,
            'receiver_anonymous_id' => $recipient->id,
            'message' => 'Szerkesztői megkeresés',
        ]);

        $this->withSession(['anonymous_token' => $recipient->token]);

        $this->get(route('contact.form'))
            ->assertOk()
            ->assertSee('aria-label="1 olvasatlan üzenet"', false)
            ->assertSee('href="'.route('contact.inbox').'"', false);
    }

    public function testHeaderHasNoUnreadBadgeWithoutUnreadMessages(): void
    {
        $recipient = AnonymousId::factory()->create();
        $this->withSession(['anonymous_token' => $recipient->token]);

        $this->get(route('contact.form'))
            ->assertOk()
            ->assertDontSee('olvasatlan üzenet');
    }

    public function testOpeningThreadMarksUnreadRepliesAsRead(): void
    {
        $user = AnonymousId::factory()->create();
        $root = ContactMessage::create([
            'sender_anonymous_id' => $user->id,
            'message' => 'Felhasználói kérdés',
            'is_read' => true,
        ]);
        $reply = ContactMessage::create([
            'sender_anonymous_id' => $this->editor->id,
            'receiver_anonymous_id' => $user->id,
            'parent_id' => $root->id,
            'message' => 'Szerkesztői válasz',
            'is_read' => false,
        ]);
        $otherThreadReply = ContactMessage::create([
            'sender_anonymous_id' => $this->editor->id,
            'receiver_anonymous_id' => $user->id,
            'message' => 'Másik beszélgetés',
            'is_read' => false,
        ]);

        $this->withSession(['anonymous_token' => $user->token]);

        $this->get(route('contact.thread', $root->id))->assertOk();

        $this->assertTrue($reply->fresh()->is_read);
        $this->assertFalse($otherThreadReply->fresh()->is_read);
    }

    public function testRecipientSeesMessageInInboxAndCanReply(): void
    {
        $recipient = AnonymousId::factory()->create();
        $root = ContactMessage::create([
            'sender_anonymous_id' => $this->editor->id,
            'receiver_anonymous_id' => $recipient->id,
            'message' => 'Szerkesztői megkeresés',
        ]);

        $this->withSession(['anonymous_token' => $recipient->token]);

        $this->get(route('contact.inbox'))
            ->assertOk()
            ->assertSee('Szerkesztői megkeresés');

        $this->get(route('contact.thread', $root->id))
            ->assertOk()
            ->assertSee('Szerkesztői megkeresés');

        $this->post(route('contact.reply', $root->id), [
            'message' => 'Köszönöm, válaszolok.',
        ])->assertRedirect(route('contact.thread', ['message' => $root->id]));

        $reply = ContactMessage::query()->where('parent_id', $root->id)->first();
        $this->assertNotNull($reply);
        $this->assertSame($recipient->id, $reply->sender_anonymous_id);
        $this->assertSame($this->editor->id, $reply->receiver_anonymous_id);
    }
}
