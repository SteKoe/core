<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\DAV\CardDAV;

use OCA\DAV\DAV\Sharing\IShareable;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

class AddressBook extends \Sabre\CardDAV\AddressBook implements IShareable {

	/**
	 * Updates the list of shares.
	 *
	 * The first array is a list of people that are to be added to the
	 * addressbook.
	 *
	 * Every element in the add array has the following properties:
	 *   * href - A url. Usually a mailto: address
	 *   * commonName - Usually a first and last name, or false
	 *   * summary - A description of the share, can also be false
	 *   * readOnly - A boolean value
	 *
	 * Every element in the remove array is just the address string.
	 *
	 * @param array $add
	 * @param array $remove
	 * @return void
	 */
	function updateShares(array $add, array $remove) {
		/** @var CardDavBackend $carddavBackend */
		$carddavBackend = $this->carddavBackend;
		$carddavBackend->updateShares($this, $add, $remove);
	}

	/**
	 * Returns the list of people whom this addressbook is shared with.
	 *
	 * Every element in this array should have the following properties:
	 *   * href - Often a mailto: address
	 *   * commonName - Optional, for example a first + last name
	 *   * status - See the Sabre\CalDAV\SharingPlugin::STATUS_ constants.
	 *   * readOnly - boolean
	 *   * summary - Optional, a description for the share
	 *
	 * @return array
	 */
	function getShares() {
		/** @var CardDavBackend $carddavBackend */
		$carddavBackend = $this->carddavBackend;
		return $carddavBackend->getShares($this->getResourceId());
	}

	function getACL() {
		$acl = parent::getACL();
		if ($this->getOwner() === 'principals/system/system') {
			$acl[] = [
					'privilege' => '{DAV:}read',
					'principal' => '{DAV:}authenticated',
					'protected' => true,
			];
		}

		// add the current user
		if (isset($this->addressBookInfo['{http://owncloud.org/ns}owner-principal'])) {
			$owner = $this->addressBookInfo['{http://owncloud.org/ns}owner-principal'];
			$acl[] = [
					'privilege' => '{DAV:}read',
					'principal' => $owner,
					'protected' => true,
				];
			if ($this->addressBookInfo['{http://owncloud.org/ns}read-only']) {
				$acl[] = [
					'privilege' => '{DAV:}write',
					'principal' => $owner,
					'protected' => true,
				];
			}
		}

		/** @var CardDavBackend $carddavBackend */
		$carddavBackend = $this->carddavBackend;
		return $carddavBackend->applyShareAcl($this->getResourceId(), $acl);
	}

	function getChildACL() {
		$acl = parent::getChildACL();
		if ($this->getOwner() === 'principals/system/system') {
			$acl[] = [
					'privilege' => '{DAV:}read',
					'principal' => '{DAV:}authenticated',
					'protected' => true,
			];
		}

		/** @var CardDavBackend $carddavBackend */
		$carddavBackend = $this->carddavBackend;
		return $carddavBackend->applyShareAcl($this->getResourceId(), $acl);
	}

	function getChild($name) {
		$obj = $this->carddavBackend->getCard($this->getResourceId(), $name);
		if (!$obj) {
			throw new NotFound('Card not found');
		}
		return new Card($this->carddavBackend, $this->addressBookInfo, $obj);
	}

	/**
	 * @return int
	 */
	public function getResourceId() {
		return $this->addressBookInfo['id'];
	}

	function getOwner() {
		if (isset($this->addressBookInfo['{http://owncloud.org/ns}owner-principal'])) {
			return $this->addressBookInfo['{http://owncloud.org/ns}owner-principal'];
		}
		return parent::getOwner();
	}

	function delete() {
		if (isset($this->addressBookInfo['{http://owncloud.org/ns}owner-principal'])) {
			$principal = 'principal:' . parent::getOwner();
			$shares = $this->getShares();
			$shares = array_filter($shares, function($share) use ($principal){
				return $share['href'] === $principal;
			});
			if (empty($shares)) {
				throw new Forbidden();
			}

			/** @var CardDavBackend $cardDavBackend */
			$cardDavBackend = $this->carddavBackend;
			$cardDavBackend->updateShares($this, [], [
				'href' => $principal
			]);
			return;
		}
		parent::delete();
	}

	function propPatch(PropPatch $propPatch) {
		if (isset($this->addressBookInfo['{http://owncloud.org/ns}owner-principal'])) {
			throw new Forbidden();
		}
		parent::propPatch($propPatch);
	}

	public function getContactsGroups() {
		/** @var CardDavBackend $cardDavBackend */
		$cardDavBackend = $this->carddavBackend;

		return $cardDavBackend->collectCardProperties($this->getResourceId(), 'CATEGORIES');
	}
}
