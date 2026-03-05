<?php

namespace App\Enums\Store;

enum StoreConnectionStatus: string
{
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';
}
