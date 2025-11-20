<?php
function getProfileImageUrl($profileImage) {
    // Default avatar
    $defaultAvatar = 'https://ui-avatars.com/api/?name=Admin&background=random&size=150';

    // If empty or null, return default
    if (empty($profileImage)) {
        return $defaultAvatar;
    }

    // If it's a full URL already
    if (filter_var($profileImage, FILTER_VALIDATE_URL)) {
        return $profileImage;
    }

    // This is the path from the web root, used for the `src` attribute.
    // It assumes the `main.php` is at `/production/profile/php/main.php`
    // and the images are in `/production/profile/php/uploads/profile_images/`
    $relativeUrl = 'uploads/profile_images/' . $profileImage;

    // This is the absolute file system path to check for existence.
    // __DIR__ is `.../production/profile/functions`
    $localPath = __DIR__ . '/../php/uploads/profile_images/' . $profileImage;

    // Check if file exists on disk
    if (file_exists($localPath)) {
        // We need to return a URL relative to the `main.php` file.
        // `main.php` is in `/production/profile/php/`.
        // The image is at `/production/profile/php/uploads/profile_images/`.
        // So the relative URL from `main.php` is correct.
        return $relativeUrl;
    }

    // Fallback if file not found
    return $defaultAvatar;
} 