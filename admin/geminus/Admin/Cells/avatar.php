<span class="avatar" <?= $user->avatar ? 'style="background-image:url(' . $user->getAvatarUrl() . ')"' : '' ?>><?= esc(mb_substr($user->username, 0, 2)) ?></span>
