<?php

namespace OCA\DAV\Avatars;

use OCP\Files\NotFoundException;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;
use OCP\IAvatarManager;

class AvatarCollection implements ICollection {

	/** @var IAvatarManager */
	private $manager;

	/** @var array */
	private $principalInfo;

	/**
	 * FilesHome constructor.
	 *
	 * @param array $principalInfo
	 * @param IAvatarManager $manager
	 */
	public function __construct($principalInfo, $manager) {
		$this->principalInfo = $principalInfo;
		$this->manager = $manager;
	}

	/**
	 * Creating new files is not allowed here
	 *
	 * @param string $name
	 * @param resource|string $data
	 * @throws Forbidden
	 */
	public function createFile($name, $data = null) {
		throw new Forbidden('Permission denied to create files');
	}

	/**
	 * Creating new directories is not allowed here
	 *
	 * @param string $name
	 * @throws Forbidden
	 */
	public function createDirectory($name) {
		throw new Forbidden('Permission denied to create folders');
	}

	/**
	 * Get an avatar of a given size
	 *
	 * @param string $name
	 * @return Avatar
	 * @throws NotFound
	 * @throws MethodNotAllowed
	 */
	public function getChild($name) {
		$user = $this->getName();
		$info = pathinfo($name);

		$ext = isset($info['extension']) ? $info['extension'] : '';
		$size = ctype_digit($info['filename']) ? intval($info['filename']) : 0;

		// We only support jpg and png avatars
		if (!in_array($ext, ['jpg', 'png'], true)) {
			throw new MethodNotAllowed('File format not allowed');
		}

		// Check for valid avatar size
		if ($size < 1 || $size > 2048) {
			throw new MethodNotAllowed('Invalid image size');
		}

		// See if there is an avatar
		try {
			$avatar = $this->manager->getAvatar($user)->getFile($size);
		} catch (NotFoundException $e) {
			throw new NotFound();
		}

		// Check that the avatar matches the requested file type
		if (($avatar->getMimeType() === 'image/png' && $ext !== 'png') ||
			($avatar->getMimeType() === 'image/jpeg' && $ext !== 'jpg')) {
			throw new NotFound();
		}

		return new Avatar($avatar);
	}

	/**
	 * Do not list avatars
	 *
	 * @throws Forbidden
	 */
	public function getChildren() {
		throw new Forbidden('Listing of avatars not allowed. Use /SIZE to get an avatar of size');
	}

	/**
	 * A child exists if it is an integer larger than 0
	 *
	 * @param string $name
	 * @return bool
	 */
	public function childExists($name) {
		$avatar = $this->getChild($name);
		return !is_null($avatar);
	}

	/**
	 * Not allowed to delete avatars via this endpoint
	 *
	 * @throws Forbidden
	 */
	public function delete() {
		throw new Forbidden('Permission denied to delete');
	}

	/**
	 * The name if just the name of the principal
	 *
	 * @return mixed
	 */
	public function getName() {
		list(,$name) = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	/**
	 * Now allowed to update name
	 *
	 * @param string $name
	 * @throws Forbidden
	 */
	public function setName($name) {
		throw new Forbidden('Permission denied to rename this folder');
	}

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int
	 */
	public function getLastModified() {
	}

}