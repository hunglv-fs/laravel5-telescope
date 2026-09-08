<?php namespace HungLv\Telescope\Watchers;

use Exception;
use Swift_Message;
use HungLv\Telescope\Telescope;
use HungLv\Telescope\EntryType;
use HungLv\Telescope\IncomingEntry;
use HungLv\Telescope\Support\Sanitizer;

class MailWatcher extends Watcher {

	/**
	 * {@inheritdoc}
	 */
	public function register($app)
	{
		$watcher = $this;

		$app['events']->listen('mailer.sending', function($message) use ($watcher)
		{
			$watcher->record($message);
		});
	}

	/**
	 * @param  \Swift_Message  $message
	 * @return void
	 */
	public function record($message)
	{
		if ( ! Telescope::isRecording()) return;

		try
		{
			$content = [
				'subject' => $message->getSubject(),
				'from'    => $this->addresses($message->getFrom()),
				'to'      => $this->addresses($message->getTo()),
				'cc'      => $this->addresses($message->getCc()),
				'bcc'     => $this->addresses($message->getBcc()),
				'body'    => Sanitizer::string((string) $message->getBody(), 20000),
			];

			Telescope::record(EntryType::MAIL, IncomingEntry::make($content)->tags($this->addresses($message->getTo())));
		}
		catch (Exception $e)
		{
			Telescope::report($e);
		}
	}

	/**
	 * @param  array|null  $addresses
	 * @return array
	 */
	protected function addresses($addresses)
	{
		return is_array($addresses) ? array_keys($addresses) : [];
	}

}
