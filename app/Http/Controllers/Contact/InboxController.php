<?php

namespace SzentirasHu\Http\Controllers\Contact;

use Illuminate\Support\Facades\Session;
use SzentirasHu\Data\Entity\AnonymousId;
use SzentirasHu\Data\Entity\ContactMessage;
use SzentirasHu\Http\Controllers\Controller;

class InboxController extends Controller
{
    public function index()
    {
        $token = Session::get('anonymous_token');
        if (!$token) {
            return redirect()->route('contact.form');
        }

        $anonymousId = AnonymousId::where('token', $token)->first();
        if (!$anonymousId) {
            return redirect()->route('contact.form');
        }

        $threads = ContactMessage::rootMessages()
            ->forParticipant($anonymousId)
            ->with(['sender', 'replies'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Compute additional thread attributes
        $threads->each(function ($thread) use ($anonymousId) {
            $thread->total_replies = $thread->replies->count();
            $thread->latest_activity = $thread->created_at;
            if ($thread->replies->isNotEmpty()) {
                $thread->latest_activity = $thread->replies->max('created_at');
            }
            // Check if any message in thread is unread (root or replies)
            $thread->has_unread = !$thread->is_read || $thread->replies->contains('is_read', false);
            // Determine if the user is the sender of the root message
            $thread->user_is_sender = $thread->sender_anonymous_id === $anonymousId->id;
        });

        // Mark unread root messages as read when viewing inbox
        $unreadRootIds = $threads->filter(fn($thread) => !$thread->is_read)->pluck('id');
        if ($unreadRootIds->isNotEmpty()) {
            ContactMessage::whereIn('id', $unreadRootIds)->update(['is_read' => true]);
        }

        return view('contact.inbox', [
            'threads' => $threads,
            'anonymousId' => $anonymousId,
        ]);
    }

    public function showThread($messageId)
    {
        $token = Session::get('anonymous_token');
        if (!$token) {
            return redirect()->route('contact.form');
        }

        $anonymousId = AnonymousId::where('token', $token)->first();
        if (!$anonymousId) {
            return redirect()->route('contact.form');
        }

        $message = ContactMessage::with(['sender', 'receiver', 'replies.sender'])
            ->findOrFail($messageId);

        // Ensure user is either sender or receiver of this thread
        $isParticipant = $message->sender_anonymous_id === $anonymousId->id
            || $message->receiver_anonymous_id === $anonymousId->id;

        if (!$isParticipant) {
            abort(403);
        }

        ContactMessage::query()
            ->where(fn ($query) => $query->where('id', $message->id)->orWhere('parent_id', $message->id))
            ->where('receiver_anonymous_id', $anonymousId->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return view('contact.thread', [
            'message' => $message,
            'anonymousId' => $anonymousId,
        ]);
    }

    public function reply($messageId)
    {
        $token = Session::get('anonymous_token');
        if (!$token) {
            return redirect()->route('contact.form');
        }

        $anonymousId = AnonymousId::where('token', $token)->first();
        if (!$anonymousId) {
            return redirect()->route('contact.form');
        }

        $parentMessage = ContactMessage::findOrFail($messageId);

        // Ensure user is either sender or receiver of this thread
        $isParticipant = $parentMessage->sender_anonymous_id === $anonymousId->id
            || $parentMessage->receiver_anonymous_id === $anonymousId->id;

        if (!$isParticipant) {
            abort(403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make(request()->all(), [
            'message' => 'required|string|min:5|max:2000',
        ]);

        if ($validator->fails()) {
            return redirect()->route('contact.thread', ['message' => $messageId])
                ->withErrors($validator)
                ->withInput();
        }

        // Determine who is the receiver (the other participant)
        $receiverId = $parentMessage->sender_anonymous_id === $anonymousId->id
            ? $parentMessage->receiver_anonymous_id
            : $parentMessage->sender_anonymous_id;

        ContactMessage::create([
            'sender_anonymous_id' => $anonymousId->id,
            'receiver_anonymous_id' => $receiverId,
            'parent_id' => $parentMessage->id,
            'message' => request('message'),
            'is_read' => false,
        ]);

        return redirect()->route('contact.thread', ['message' => $messageId])
            ->with('success', 'Válaszod elküldve.');
    }
}