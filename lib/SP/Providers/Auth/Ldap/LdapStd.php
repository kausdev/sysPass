<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Providers\Auth\Ldap;

use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;

/**
 * Class LdapStd
 *
 * Autentificación basada en LDAP estándard
 *
 * @package SP\Auth\Ldap
 */
final class LdapStd extends Ldap
{
    const FILTER_USER_OBJECT = '(|(objectClass=inetOrgPerson)(objectClass=person)(objectClass=simpleSecurityObject))';
    const FILTER_GROUP_OBJECT = '(|(objectClass=groupOfNames)(objectClass=groupOfUniqueNames)(objectClass=group))';
    const FILTER_USER_ATTRIBUTES = ['samaccountname', 'cn', 'uid', 'userPrincipalName'];
    const FILTER_GROUP_ATTRIBUTES = ['memberOf', 'groupMembership'];

    /**
     * Devolver el filtro para objetos del tipo grupo
     *
     * @return string
     */
    public function getGroupObjectFilter(): string
    {
        return self::FILTER_GROUP_OBJECT;
    }

    /**
     * Devolver el filtro para comprobar la pertenecia al grupo
     *
     * @return string
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getGroupMembershipFilter(): string
    {
        if (empty($this->ldapParams->getGroup())) {
            return self::FILTER_USER_OBJECT;
        }

        return '(&(|'
            . LdapUtil::getAttributesForFilter(
                self::FILTER_GROUP_ATTRIBUTES,
                $this->getGroupDn())
            . ')' . self::FILTER_USER_OBJECT
            . ')';
    }

    /**
     * Obtener el filtro para buscar el usuario
     *
     * @param string $userLogin
     *
     * @return mixed
     */
    public function getUserDnFilter(string $userLogin): string
    {
        return '(&(|'
            . LdapUtil::getAttributesForFilter(self::FILTER_USER_ATTRIBUTES, $userLogin)
            . ')'
            . self::FILTER_USER_OBJECT
            . ')';
    }

    /**
     * Buscar al usuario en un grupo.
     *
     * @param string $userDn
     * @param array  $groupsDn
     *
     * @return bool
     * @throws LdapException
     */
    public function isUserInGroup(string $userDn, array $groupsDn): bool
    {
        // Comprobar si está establecido el filtro de grupo o el grupo coincide con
        // los grupos del usuario
        if (empty($this->ldapParams->getGroup())
            || $this->ldapParams->getGroup() === '*'
            || in_array($this->getGroupDn(), $groupsDn)
        ) {
            $this->eventDispatcher->notifyEvent('ldap.check.group',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Usuario verificado en grupo'))
                    ->addDetail(__u('Usuario'), $userDn)
                    ->addDetail(__u('Grupo'), $this->getGroupDn())));

            return true;
        }

        return $this->checkUserInGroupByFilter($userDn);
    }

    /**
     * @param string $userDn
     *
     * @return bool
     * @throws LdapException
     */
    private function checkUserInGroupByFilter(string $userDn): bool
    {
        $groupName = ldap_escape($this->getGroupFromParams(), null, LDAP_ESCAPE_FILTER);
        $userDN = ldap_escape($userDn, null, LDAP_ESCAPE_FILTER);
        $filter = '(&(cn=' . $groupName . ')'
            . '(|(memberUid=' . $userDN . ')(member=' . $userDN . ')(uniqueMember=' . $userDN . '))'
            . self::FILTER_GROUP_OBJECT
            . ')';

        $searchResults = $this->ldapActions->getObjects($filter, ['dn']);

        if (isset($searchResults['count'])
            && (int)$searchResults['count'] === 0
        ) {
            $this->eventDispatcher->notifyEvent('ldap.check.group',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Usuario no pertenece al grupo'))
                    ->addDetail(__u('Usuario'), $userDn)
                    ->addDetail(__u('Grupo'), $groupName)
                    ->addDetail('LDAP FILTER', $filter)));

            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws \SP\Core\Exceptions\SPException
     */
    public function connect()
    {
        $handler = parent::connect();

        @ldap_set_option($handler, LDAP_OPT_REFERRALS, 0);

        return true;
    }

    /**
     * Obtener el servidor de LDAP a utilizar
     *
     * @return mixed
     */
    protected function pickServer()
    {
        return $this->ldapParams->getServer();
    }
}