<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Authentication\Auth;

use OrangeHRM\Authentication\Dto\AuthParamsInterface;
use OrangeHRM\Authentication\Dto\UserCredentialInterface;
use OrangeHRM\Authentication\Exception\AuthenticationException;
use OrangeHRM\Authentication\Service\AuthenticationService;

class LocalAuthProvider extends AbstractAuthProvider
{
    private AuthenticationService $authenticationService;

    /**
     * @return AuthenticationService
     */
    private function getAuthenticationService(): AuthenticationService
    {
        return $this->authenticationService ??= new AuthenticationService();
    }

    /**
     * @param AuthParamsInterface $authParams
     * @return bool
     * @throws AuthenticationException
     */
    public function authenticate(AuthParamsInterface $authParams): bool
    {
        if (!$authParams->getCredential() instanceof UserCredentialInterface) {
            return false;
        }
        $success = $this->getAuthenticationService()->setCredentials($authParams->getCredential());
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return 10000;
    }
}
