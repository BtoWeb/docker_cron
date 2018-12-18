<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 18/12/2018
 * Time: 14:14
 */

namespace App\Scheduler;


use Doctrine\ORM\EntityRepository;
use JMS\JobQueueBundle\Cron\JobScheduler;

class CronTaskSchedulerList implements \Iterator, \ArrayAccess {
	/**
	 * Liste des schedulers
	 * @var JobScheduler[]
	 */
	protected $schedulers;

	/**
	 * Liste des clés (ordonnée !)
	 * @var string[]
	 */
	protected $keys;

	/**
	 * Position courante dans le tableau
	 * @var int
	 */
	protected $i;




	/**
	 * CronTaskSchedulerList constructor.
	 *
	 * @param JobScheduler[] $schedulers
	 * @param EntityRepository $cronTaskRepository
	 */
	public function __construct( $schedulers, EntityRepository $cronTaskRepository ) {
		$this->schedulers = $schedulers;
		$this->keys       = array_keys( $this->schedulers );
		$this->i          = 0;

		$this->cronTaskRepository = $cronTaskRepository;
	}

	/**
	 * Return the current element
	 * @link https://php.net/manual/en/iterator.current.php
	 * @return mixed Can return any type.
	 * @since 5.0.0
	 */
	public function current() {
		return $this->schedulers[ $this->keys[ $this->i ] ];
	}

	/**
	 * Move forward to next element
	 * @link https://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 * @since 5.0.0
	 */
	public function next() {
		$this->i ++;
	}

	/**
	 * Return the key of the current element
	 * @link https://php.net/manual/en/iterator.key.php
	 * @return mixed scalar on success, or null on failure.
	 * @since 5.0.0
	 */
	public function key() {
		return $this->keys[ $this->i ];
	}

	/**
	 * Checks if current position is valid
	 * @link https://php.net/manual/en/iterator.valid.php
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 * Returns true on success or false on failure.
	 * @since 5.0.0
	 */
	public function valid() {
		return $this->i < count( $this->keys );
	}

	/**
	 * Rewind the Iterator to the first element
	 * @link https://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 * @since 5.0.0
	 */
	public function rewind() {
		$this->i = 0;
	}

	/**
	 * Whether a offset exists
	 * @link https://php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 *
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 * @since 5.0.0
	 */
	public function offsetExists( $offset ) {
		return isset( $this->schedulers[ $offset ] );
	}

	/**
	 * Offset to retrieve
	 * @link https://php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 *
	 * @return mixed Can return all value types.
	 * @since 5.0.0
	 */
	public function offsetGet( $offset ) {
		return $this->schedulers[ $offset ];
	}

	/**
	 * Offset to set
	 * @link https://php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetSet( $offset, $value ) {
		$this->schedulers[ $offset ] = $value;
		$this->keys                  = array_keys( $this->schedulers );
	}

	/**
	 * Offset to unset
	 * @link https://php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function offsetUnset( $offset ) {
		unset( $this->schedulers[ $offset ] );
		$this->keys = array_keys( $this->schedulers );
	}
}