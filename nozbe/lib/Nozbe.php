<?php
/**
 * Nozbe API client
 *
 * PHP Version 5.2.0 or Upper version
 *
 * @package    Nozbe
 * @author     Hidehito NOZAWA aka Suin <suinyeze@gmail.com>
 * @copyright  2011 Hidehito NOZAWA
 * @license    MIT license
 *
 */

class Nozbe
{
	protected $options = array(
		'baseUrl'     => 'http://www.nozbe.com/api',
		'httpTimeout' => 10,
	);

	protected $apiKey = null;
	protected $streamContext = array();

	public function __construct(array $options = array())
	{
		$this->options = array_merge($this->options, $options);
		$this->_initStreamContext();
	}

	/**
	 * Login and get API key
	 * @param string $email Nozbe account username
	 * @param string $password Nozbe account password
	 * @return bool returns TRUE on success or FALSE on failure
	 */
	public function login($email, $password)
	{
		$apiKey = $this->fetchApiKey($email, $password);

		if ( $apiKey === null )
		{
			return false;
		}

		$this->apiKey = $apiKey;
		return true;
	}

	/**
	 * Returns API key
	 * @param string $email Nozbe account username
	 * @param string $password Nozbe account password
	 * @return string returns API key on succes
	 */
	public function fetchApiKey($email, $password)
	{
		$params = array('email' => $email, 'password' => $password);
		$data   = $this->call('login', $params, false);
		return $data['key'];
	}

	public function getApiKey()
	{
		return $this->apiKey;
	}

	public function setApiKey($apiKey)
	{
		return $this->apiKey = $apiKey;
	}

	/**
	 * Returns projects
	 * @return array returns list of porjects
	 */
	public function getProjects()
	{
		return $this->call('projects');
	}

	public function getProjectNames()
	{
		$projects = $this->getProjects();
		return $this->_hashToKeyValue($projects, 'id', 'name');
	}

	public function projectExists($id)
	{
		$projects = $this->getProjects();
		return $this->_find($projects, 'id', $id);
	}

	/**
	 * Returns contexts
	 * @return array returns list of contexts
	 */
	public function getContexts()
	{
		return $this->call('contexts');
	}

	public function getContextNames()
	{
		$contexts = $this->getContexts();
		return $this->_hashToKeyValue($contexts, 'id', 'name');
	}

	public function contextExists($id)
	{
		$contexts = $this->getContexts();
		return $this->_find($contexts, 'id', $id);
	}

	public function getNextActions()
	{
		return $this->getActions('next');
	}

	public function getNextActionNames()
	{
		$actions = $this->getNextActions();
		return $this->_hashToKeyValue($actions, 'id', 'name');
	}

	public function nextActionExists($id)
	{
		$actions = $this->getNextActions();
		return $this->_find($actions, 'id', $id);
	}

	public function getProjectActions($id, $showsDone = false)
	{
		return $this->getActions('project', $id, $showsDone);
	}

	public function getProjectActionNames($id, $showsDone = false)
	{
		$actions = $this->getProjectActions($id, $showsDone);
		return $this->_hashToKeyValue($actions, 'id', 'name');
	}

	public function projectActionExists($id, $includeDoneActions = false)
	{
		$actions = $this->getProjectActions($id, $includeDoneActions);
		return $this->_find($actions, 'id', $id);
	}

	public function getContextActions($id, $showsDone = false)
	{
		return $this->getActions('context', $id, $showsDone);
	}

	public function getContextActionNames($id, $showsDone = false)
	{
		$actions = $this->getContextActions($id, $showsDone);
		return $this->_hashToKeyValue($actions, 'id', 'name');
	}

	public function contextActionExists($id, $includeDoneActions = false)
	{
		$actions = $this->getContextActions($id, $includeDoneActions);
		return $this->_find($actions, 'id', $id);
	}

	public function getActions($what, $id = null, $showsDone = false)
	{
		$params = array(
			'what' => $what,
		);

		if ( $id !== null )
		{
			$params['id'] = $id;
		}

		if ( $showsDone === true )
		{
			$params['showdone'] = '1';
		}

		$actions = $this->call('actions', $params);

		foreach ( $actions as &$action )
		{
			if ( $action['done_time'] == '0' )
			{
				$action['done_time'] = false;
			}
			else
			{
				$action['done_time'] = strtotime($action['done_time'].' GMT'); // nozbe returns GMT, but not documented.
			}

			$action['next'] = intval( $action['next'] == 'next' );
		}

		return $actions;
	}

	public function addAction($name, $projectId = null, $contextId = null, $time = null, $next = true)
	{
		$params = array(
			'name' => urlencode($name),
		);

		if ( $projectId )
		{
			$params['project_id'] = $projectId;
		}

		if ( $contextId )
		{
			$params['context_id'] = $contextId;
		}

		if ( $time )
		{
			$params['time'] = $time;
		}

		if ( $next )
		{
			$params['next'] = 'true';
		}

		return $this->call('newaction', $params);
	}

	public function doneActions(array $ids)
	{
		$ids = implode(';', $ids);
		$response = $this->call('check', array('ids' => $ids));

		if ( isset($response['response']) === true and $response['response'] === 'ok' )
		{
			return true; // it seems always to returns 'ok' even if invalid pass invalid ids :(
		}

		return false;
	}

	public function doneAction($id)
	{
		return $this->doneActions(array($id));
	}

	/**
	 * Call API method
	 * @param string $method API method name
	 * @param array $params parameters
	 * @param bool $addApiKey include API key to request on TRUE
	 * @return array returns result
	 */
	public function call($method, array $params = array(), $addApiKey = true)
	{
		$methodUrl = $this->_getMethodUrl($method, $params, $addApiKey);
		$response  = $this->_httpGet($methodUrl);
		$data      = $this->_jsonToArray($response);
		return $data;
	}

	protected function _initStreamContext()
	{
		$this->streamContext = stream_context_create(
			array(
				'http' => array(
					'timeout' => $this->options['httpTimeout'],
				),
			)
		);
	}

	protected function _jsonToArray($json)
	{
		$data = json_decode($json, true);

		if ( $data === null )
		{
			throw new NozbeException("Invalid json data: $json");
		}

		return $data;
	}

	protected function _httpGet($url)
	{
		$response = file_get_contents($url , false, $this->streamContext);

		if ( $response === false )
		{
			throw new NozbeException("Failed to http request: $url");
		}

		return $response;
	}

	protected function _getMethodUrl($method, array $params = array(), $addApiKey = true)
	{
		$methodUrl = $this->options['baseUrl'].'/'.$method.'/';

		foreach ( $params as $name => $value )
		{
			$methodUrl .= $name.'-'.$value.'/';
		}

		if ( $addApiKey === true )
		{
			$methodUrl .= 'key-'.$this->apiKey.'/';
		}

		return $methodUrl;
	}

	protected function _hashToKeyValue(array $data, $keyName, $valueName)
	{
		$keyValueData = array();

		foreach ( $data as $datum )
		{
			$key   = $datum[$keyName];
			$value = $datum[$valueName];
			$keyValueData[$key] = $value;
		}

		return $keyValueData;
	}

	protected function _find(array $data, $key, $value)
	{
		foreach ( $data as $datum )
		{
			if ( $datum[$key] == $value )
			{
				return true;
			}
		}

		return false;
	}
}

class NozbeException extends Exception
{
}
