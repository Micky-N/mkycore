<?php

namespace MkyCore\EventNotification;

use MkyCore\RelationEntity\HasMany;

class HasManyUnReadNotification extends HasMany
{

    public function markAsRead()
    {
        /** @var Notification[] $notifications */
        $notifications = $this->get();
        for ($i = 0; $i < count($notifications); $i++) {
            $notification = $notifications[$i];
            $notification->markAsRead();
        }
    }
}