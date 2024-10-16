<?php

// Copied from https://github.com/JaapRood/php-resque-plugin/blob/master/lib/Resque/Plugin.php
class Resque_Plugin {

	/**
	 * @var  array  of plugin instances, ordered by job 
	 */
	protected static $_pluginInstances = array();

	/**
	 * @var  array  of hooks to listen for
	 */
	protected static $_hooks = array(
		'beforePerform',
		'afterPerform',
		'onFailure'
	);

	/**
	 * Start listening to Resque_Event and start using those plugis
	 */
	public static function initialize() {
		$hooks = static::$_hooks;
		$class = get_called_class();
		$notifyMethod = $class ."::notify_plugins";

		foreach ($hooks as $hook) {
			Resque_Event::listen($hook, function($job = null, $exception = null) use ($notifyMethod, $hook) {
				$payload = func_get_args();
				array_unshift($payload, $hook);

				call_user_func_array($notifyMethod, $payload);
			});
		}
	}

	/**
	 * @param  string 	$hook 			which hook to run
	 * @param  mixed 	$jobOrFailure 	job for which to run the plugins
	 */
	public static function notify_plugins($hook, $jobOrFailure, $job = null) {
		if ($jobOrFailure instanceof Resque_Job) {
			$possibleException = $job;
			$job = $jobOrFailure;
			if($possibleException instanceof Throwable) {
				$exception = $possibleException;
			} else {
				$exception = null;
			}
		} elseif ($jobOrFailure instanceof Exception) {
			$exception = $jobOrFailure;
		} else {
			// TODO: review this choice, not sure if it's the right thing to do
			return; // fail silently if we don't know how to handle this
		}

		$plugins = static::plugins($job, $hook);

		foreach ($plugins as $plugin) {
			$callable = array($plugin, $hook);
			if (is_callable($callable)) {
				$payload = array($job, $job->getInstance());
				
				if (!is_null($exception)) {
					array_unshift($payload, $exception);
				}

				call_user_func_array($callable, $payload);
			}
		}
	} 

	/**
	 * Retrieve the plugin instances for this job, optionally filtered by a hook
	 * 
	 * @param  Resque_Job 	$job  	an instance of a job
	 * @param  string 		$hook 	optional hook to filter by
	 * @return array 	of plugins for the job
	 */
	public static function plugins(Resque_Job $job, $hook = null) {
		$jobName = (string) $job;

		if (!array_key_exists($jobName, static::$_pluginInstances)) {
			static::$_pluginInstances[$jobName] = static::createInstances($job);
		}

		$instances = static::$_pluginInstances[$jobName];

		if (empty($hook) or empty($instances)) {
			return $instances;
		}

		return array_filter($instances, function($instance) use ($hook) {
			return is_callable(array($instance, $hook));
		});
	}

	/**
	 * Create instances of the plugins for the specified job class
	 * @param 	Resque_Job	$job 
	 * @return  array  		of plugin instances for this job class
	 */
	public static function createInstances($job) {
		$instances = array();
		$jobClass = $job->getClass();

		if (property_exists($jobClass, 'resquePlugins')) {
			$pluginClasses = $jobClass::$resquePlugins;

			foreach ($pluginClasses as $pluginClass) {
				if (stripos($pluginClass, '\\') !== 0) {
					$pluginClass = '\\'. $pluginClass;
				}

				if (class_exists($pluginClass)) {
					array_push($instances, new $pluginClass);
				}
			}
		}

		return $instances;
	}


}