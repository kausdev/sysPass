<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2017, Rubén Domínguez nuxsmin@$syspass.org
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

namespace SP\Services\Import;

use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;

defined('APP_ROOT') || die();

/**
 * Class CsvImport para importar cuentas desde archivos CSV
 *
 * @package SP
 */
class CsvImport extends CsvImportBase implements ImportInterface
{
    /**
     * Iniciar la importación desde XML.
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function doImport()
    {
        $this->eventDispatcher->notifyEvent('run.import.csv',
            new Event($this,
                EventMessage::factory()
                    ->addDescription(sprintf(__('Formato detectado: %s'), 'CSV')))
        );

        $this->fileImport->readFileToArray();
        $this->processAccounts();

        return $this;
    }
}