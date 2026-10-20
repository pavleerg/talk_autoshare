# Talk recording auto-share

A Nextcloud app that shares finished Talk call recordings into the conversation chat automatically. Without it, whoever started the recording has to click **Share** on the "Call recording now available" notification.

It can also start recording as soon as a call starts, and record video calls in grid view (everyone visible) instead of Talk's speaker view.

**Requirements:** Nextcloud 34 (Hub 26 Spring) with Talk 24, and a configured Talk recording server. `appinfo/info.xml` pins Nextcloud to version 34 because the app relies on Talk internals (see [Limitations](#limitations)). Tested on Nextcloud 34.0.4 with Talk 24.0.6.

## Install

```sh
cd /var/www/html/custom_apps
git clone https://github.com/pavleerg/talk_autoshare.git
chown -R www-data:www-data talk_autoshare
sudo -u www-data php occ app:enable talk_autoshare
```

The app has no runtime dependencies, so you don't need `composer install` on the server. If Nextcloud runs in Docker, run `occ` inside the container (`docker exec -u www-data <container> php occ …`).

**Update:** run `git pull` in the app folder. If `<version>` in `info.xml` changed, also run `occ upgrade`.

**Prerequisite:** current recording servers upload in chunks through a temporary public upload link. Allow link shares and public upload under **Administration settings → Sharing**. Otherwise uploads fail with `sharing_disabled`.

## Configuration

The app does nothing until you choose which conversations it handles. Go to **Administration settings → Talk → Recording auto-share**, or use `occ`:

```sh
occ config:app:set talk_autoshare mode --value=listed          # default: only the listed conversations
occ config:app:set talk_autoshare room_tokens --value=abc123,def456
occ config:app:set talk_autoshare mode --value=group           # every group and public conversation
occ config:app:set talk_autoshare auto_record --value=yes      # start recording when a call starts
```

A conversation token is the last part of its URL (`/call/<token>`). In `group` mode, one-to-one conversations are never included. Try one listed conversation first, then switch to `group`.

## Features

### Auto-share

When the recording server uploads a recording of a handled conversation, the app shares it into that conversation's chat on behalf of the recording owner. This is the same read-only share that Talk's **Share** button creates, and the chat shows it as a file shared by the owner. The "Call recording now available" notification is then dismissed.

If anything goes wrong, the error is logged, the upload itself is unaffected, and the notification stays, so the owner can still share by hand.

There is one visible difference from clicking **Share**: the chat message lacks Talk's recording metadata, so it looks like an ordinary shared file.

### Auto-record

Auto-record is off by default. When it's on, the app starts a video recording as soon as a call starts in a handled conversation, just like clicking **Start recording**. Talk stops the recording when the call ends, and the file is then auto-shared as usual. If someone stops the recording by hand, it stays stopped for the rest of that call.

Talk only lets logged-in moderators record, so the recording (and the file in their Files) belongs to:

1. the person who started the call, if they are a moderator with an active account;
2. otherwise the conversation owner;
3. otherwise the first moderator. If none qualifies, nothing is recorded.

Starting the recording happens while the first person joins. If the recording server is slow or unreachable, that join waits for it, as it would with Talk's own button. A failure never prevents joining.

**Consent:** people are recorded without anyone clicking anything. Tell your users, or turn on Talk's recording consent so everyone has to agree before joining a call:

```sh
occ config:app:set spreed recording_consent --value=1
```

### Grid layout

Talk's recording view always records the speaker view. The app loads `js/recording-grid.js` on Talk's recording page (the page the recording server's browser opens) and keeps it in grid view for the whole recording. Screen shares still take over the view while they last. If this stops working after a Talk update, recordings fall back to the speaker view, and the recording browser's console shows a `[talk_autoshare]` warning.

## How it works

1. The recording server uploads the file to `<owner>/files/Talk/Recording/<token>/` in one of two ways. Both end in Talk's `RecordingController::store` request, where Talk also creates its notification:
   - **chunked** (Talk 24+): it requests a temporary upload link, uploads the file through WebDAV, then calls `store` with only the file name;
   - **direct**: a single multipart `store` request with the file.
2. `RecordingStoreMiddleware` is registered as a global middleware so it sees Talk's controllers. After a successful `store`, it finds the file (`ogg`, `ogv`, `mp4`, `webm` or `mkv`) in the room's recording folder.
3. If the conversation is handled, `AutoShareService` creates the room share as the owner, using a volatile session user. Talk's own share listener then posts the usual `file_shared` chat message. The app doesn't use `RecordingService::shareToChat()` because from inside `store` that would post the message twice.
4. The notification is dismissed in the same request. Talk matches notifications by exact timestamp, so the app tries every second of the request.
5. For auto-record, `CallStartedListener` reacts to Talk's `CallStartedEvent` and calls `RecordingService::start()`.

## Limitations

This app uses Talk's internal classes, which aren't a stable API, so **retest it after every Talk update**:

- **PHP:** `OCA\Talk\Config::getRecordingFolder`, `Manager::getRoomByToken`, `ParticipantService::getParticipant` and `::getParticipantsByActorType`, `RecordingService::start`, `Events\CallStartedEvent`, `Controller\RecordingController::store`, and the `Room::TYPE_*` and `Participant` type values.
- **Frontend:** `OCA.Talk.instance` (Vue 3) and its Pinia store `callView`.

A quick check after an update: record a short call in a handled conversation. It should appear in the chat in grid view, and the notification should be gone. When you upgrade to Nextcloud 35, Nextcloud disables the app because of the version pin. Test it, then raise `max-version` in `info.xml`.

## Development

```sh
composer install
composer test    # unit tests
composer lint    # php -l on lib/ and tests/
make appstore    # optional release tarball in build/, without tests and dev files
```

## License

AGPL-3.0-or-later
