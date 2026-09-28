<?php

namespace SzentirasHu\Http\Controllers\Editor;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ApiKey;
use SzentirasHu\Data\Entity\ContactMessage;
use SzentirasHu\Http\Controllers\Controller;

class ContactMessageEditorController extends Controller
{
    public function index()
    {
        $messages = ContactMessage::rootMessages()
            ->with(['sender', 'receiver', 'replies.sender'])
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return view('editor.contactMessages.index', [
            'messages' => $messages,
        ]);
    }

    public function showThread($messageId)
    {
        $message = ContactMessage::with(['sender', 'receiver', 'replies.sender'])
            ->findOrFail($messageId);

        return view('editor.contactMessages.thread', [
            'message' => $message,
        ]);
    }

    public function compose(AnonymousId $recipient): View
    {
        $apiKeys = ApiKey::query()
            ->where('created_by_anonymous_id', $recipient->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('editor.contactMessages.compose', [
            'recipient' => $recipient,
            'apiKeys' => $apiKeys,
        ]);
    }

    public function send(Request $request, AnonymousId $recipient): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $editor = AnonymousId::where('token', Session::get('anonymous_token'))->first();

        if (!$editor) {
            abort(403);
        }

        $message = ContactMessage::create([
            'sender_anonymous_id' => $editor->id,
            'receiver_anonymous_id' => $recipient->id,
            'message' => $validated['message'],
            'is_read' => false,
        ]);

        return redirect()->route('editor.contactMessages.thread', $message->id)
            ->with('success', 'Message sent.');
    }

    public function reply($messageId): RedirectResponse
    {
        $validator = Validator::make(request()->all(), [
            'reply' => 'required|string|min:1|max:2000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $original = ContactMessage::findOrFail($messageId);
        $editorToken = Session::get('anonymous_token');
        $editor = AnonymousId::where('token', $editorToken)->first();

        if (!$editor) {
            abort(403);
        }

        // Determine receiver: if original is from a guest (no sender), cannot reply
        if ($original->isGuestMessage()) {
            abort(400, 'Cannot reply to guest messages');
        }

        $reply = ContactMessage::create([
            'sender_anonymous_id' => $editor->id,
            'receiver_anonymous_id' => $original->receiver_anonymous_id ?? $original->sender_anonymous_id,
            'parent_id' => $original->id,
            'message' => request('reply'),
        ]);

        return redirect()->route('editor.contactMessages.thread', $messageId)
            ->with('success', 'Reply sent.');
    }

    public function markResolved($messageId): RedirectResponse
    {
        $message = ContactMessage::findOrFail($messageId);
        $message->markAsResolved();

        return redirect()->back()->with('success', 'Thread marked as resolved.');
    }

    public function delete($messageId): RedirectResponse
    {
        $message = ContactMessage::findOrFail($messageId);
        $message->delete();

        return redirect()->route('editor.contactMessages.index')
            ->with('success', 'Message deleted.');
    }
}