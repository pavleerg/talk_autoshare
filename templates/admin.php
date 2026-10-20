<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="talk_autoshare" class="section">
	<h2><?php p($l->t('Recording auto-share')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Finished call recordings are shared into the conversation chat automatically, on behalf of the user who started the recording.')); ?>
	</p>
	<?php if (!$_['talkAvailable']): ?>
		<p><strong><?php p($l->t('Talk 24 or newer is not enabled. Auto-share is inactive.')); ?></strong></p>
	<?php endif; ?>

	<form method="post" action="<?php p($_['saveUrl']); ?>">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">

		<p>
			<input type="radio" class="radio" id="talk_autoshare_listed" name="mode"
				value="<?php p($_['modeListed']); ?>" <?php if ($_['mode'] === $_['modeListed']) { print_unescaped('checked'); } ?>>
			<label for="talk_autoshare_listed"><?php p($l->t('Only these conversations')); ?></label>
		</p>
		<p>
			<textarea name="tokens" rows="4" cols="40"
				placeholder="<?php p($l->t('Conversation tokens, one per line')); ?>"><?php p($_['tokens']); ?></textarea>
		</p>
		<p>
			<input type="radio" class="radio" id="talk_autoshare_group" name="mode"
				value="<?php p($_['modeGroup']); ?>" <?php if ($_['mode'] === $_['modeGroup']) { print_unescaped('checked'); } ?>>
			<label for="talk_autoshare_group"><?php p($l->t('All group and public conversations (never one-to-one)')); ?></label>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="talk_autoshare_auto_record" name="autoRecord" value="1"
				<?php if ($_['autoRecord']) { print_unescaped('checked'); } ?>>
			<label for="talk_autoshare_auto_record"><?php p($l->t('Start recording automatically when a call starts (video)')); ?></label>
		</p>
		<p class="settings-hint">
			<?php p($l->t('Applies to the conversations selected above. The recording is owned by the call starter if they are a moderator, otherwise by the conversation owner, and stops when the call ends.')); ?>
		</p>

		<input type="submit" class="button primary" value="<?php p($l->t('Save')); ?>">
	</form>
</div>
