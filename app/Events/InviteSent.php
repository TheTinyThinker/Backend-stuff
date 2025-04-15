<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use App\Models\User;

class InviteSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $fromUser;
    public $roomCode;

    protected $invitedUser;

    public function __construct(User $fromUser, User $invitedUser, $roomCode)
    {
        $this->fromUser = $fromUser;
        $this->invitedUser = $invitedUser;
        $this->roomCode = $roomCode;
    }

    public function broadcastOn()
    {
        return new Channel("user.{$this->invitedUser->id}");
    }

    public function broadcastWith()
    {
        return [
            'roomCode' => $this->roomCode,
            'from' => [
                'id' => $this->fromUser->id,
                'name' => $this->fromUser->name,
                'profile_picture' => $this->fromUser->profile_picture,
            ],
        ];
    }

    public function broadcastAs()
    {
        return 'invite.sent';
    }
}
