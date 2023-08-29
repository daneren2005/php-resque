<?php

class Resque_ExponentialRetry extends Resque_Retry {

	/**
	 * Get the retry delay from the job, defaults to the amount of steps in the defined backoff
	 * strategy
	 *
	 * @param 	Resque_Job 	$job
	 * @return  int 		retry limit
	 */
	protected function retryLimit($job) {
		return $this->getInstanceProperty($job, 'retryLimit', count($this->backoffStrategy($job)));
	}

	/**
	 * Get the retry delay for the job
	 *
	 * @param 	Resque_Job 	$job
	 * @return  int 		retry delay in seconds
	 */
	protected function retryDelay($job) {
		$backoffStrategy = $this->backoffStrategy($job);
		$strategySteps = count($backoffStrategy);

		if ($strategySteps <= 0) {
			return 0;
		} elseif (($strategySteps - 1) > $job->retryAttempt) {
			return $backoffStrategy[$job->retryAttempt];
		} else {
			return $backoffStrategy[$strategySteps - 1];
		}
	}

	/**
	 * Get the backoff strategy from the job, defaults to:
	 * - 1 second
	 * - 5 seconds
	 * - 30 seconds
	 * - 1 minute
	 * - 10 minutes
	 * - 1 hour
	 * - 3 hours
	 * - 6 hours
	 *
	 * @param 	Resque_Job 	$job
	 * @return  int 		retry limit
	 */
	protected function backoffStrategy($job) {
		$defaultStrategy = array(1, 5, 30, 60, 600, 3600, 10800, 21600);
		return $this->getInstanceProperty($job, 'backoffStrategy', $defaultStrategy);
	}
}