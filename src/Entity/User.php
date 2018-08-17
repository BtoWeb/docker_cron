<?php
/**
 * Created by PhpStorm.
 * User: Utilisateur
 * Date: 19/06/2018
 * Time: 15:42
 */

namespace App\Entity;


use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity()
 * Class User
 * @package App\Entity
 */
class User implements UserInterface, \Serializable {
	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="AUTO")
	 * @var int
	 */
	protected $id;

	/**
	 * @ORM\Column(type="string", nullable=false, unique=true)
	 * @var string
	 */
	protected $username;

	/**
	 * @ORM\Column(type="string", nullable=false)
	 * @var string
	 */
	protected $password;

	/**
	 * @ORM\Column(type="string", nullable=true)
	 * @var string
	 */
	protected $salt;

	/**
	 * Returns the roles granted to the user.
	 *
	 * <code>
	 * public function getRoles()
	 * {
	 *     return array('ROLE_USER');
	 * }
	 * </code>
	 *
	 * Alternatively, the roles might be stored on a ``roles`` property,
	 * and populated in any number of different ways when the user object
	 * is created.
	 *
	 * @return (Role|string)[] The user roles
	 */
	public function getRoles() {
		return [ 'ROLE_ADMIN' ];
	}

	/**
	 * Returns the password used to authenticate the user.
	 *
	 * This should be the encoded password. On authentication, a plain-text
	 * password will be salted, encoded, and then compared to this value.
	 *
	 * @return string The password
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * Returns the salt that was originally used to encode the password.
	 *
	 * This can return null if the password was not encoded using a salt.
	 *
	 * @return string|null The salt
	 */
	public function getSalt() {
		return $this->salt;
	}

	/**
	 * Returns the username used to authenticate the user.
	 *
	 * @return string The username
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * Removes sensitive data from the user.
	 *
	 * This is important if, at any given point, sensitive information like
	 * the plain-text password is stored on this object.
	 */
	public function eraseCredentials() {

	}

	/**
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 * @since 5.1.0
	 */
	public function serialize() {
		return serialize( array(
			$this->id,
			$this->username,
			$this->password,
			$this->salt,
		) );
	}

	/**
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 *
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 *
	 * @return void
	 * @since 5.1.0
	 */
	public function unserialize( $serialized ) {
		list (
			$this->id,
			$this->username,
			$this->password,
			$this->salt
			) = unserialize( $serialized, [ 'allowed_classes' => false ] );
	}

	/**
	 * @param string $username
	 *
	 * @return User
	 */
	public function setUsername( string $username ): User {
		$this->username = $username;

		return $this;
	}

	/**
	 * @param string $password
	 *
	 * @return User
	 */
	public function setPassword( string $password ): User {
		$this->password = $password;

		return $this;
	}

	/**
	 * @param string $salt
	 *
	 * @return User
	 */
	public function setSalt( string $salt ): User {
		$this->salt = $salt;

		return $this;
	}
}