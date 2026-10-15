<?php

namespace App\Enums\Crm;

use App\Enums\Traits\HasLabels;

/**
 * The social media accounts an organisation unit can list
 */
enum SocialMedia: string
{
    use HasLabels;

    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case Snapchat = 'snapchat';
    case TikTok = 'tiktok';
    case Twitter = 'twitter';
    case Whatsapp = 'whatsapp';
    case Youtube = 'youtube';
}
