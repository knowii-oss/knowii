<?php

namespace App\Contracts\Uploads;

use App\Models\Community;
use App\Models\Upload;
use App\Models\User;

interface UploadsImages
{
    /**
     * @param  array<mixed>  $input  file (UploadedFile) and alt
     */
    public function upload(User $user, Community $community, array $input): Upload;
}
