<?php

/**
 * This file is part of MetaModels/core.
 *
 * (c) 2012-2020 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/core
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2012-2020 The MetaModels team.
 * @license    https://github.com/MetaModels/core/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace MetaModels\Test;

use Contao\PageModel;
use MetaModels\ItemList;
use PHPUnit\Framework\TestCase;
use function defined;

/**
 * Test the base attribute.
 *
 * @covers \MetaModels\ItemList
 */
final class ItemListTest extends TestCase
{
    public function testGetOutputFormat(): void
    {
        $itemlist = new ItemList();

        if (!defined('TL_MODE')) {
            define('TL_MODE', 'FE');
        }

        if (TL_MODE !== 'FE') {
            $this->markTestSkipped('Test assumes that TL_MODE is set to "FE"');
        }

        $GLOBALS['objPage'] = null;
        $this->assertSame('text', $itemlist->getOutputFormat());

        $itemlist->overrideOutputFormat('json');
        $this->assertSame('json', $itemlist->getOutputFormat());

        $itemlist->overrideOutputFormat(null);
        $GLOBALS['objPage'] = (object) ['outputFormat' => 'xhtml'];

        $this->assertSame('xhtml', $itemlist->getOutputFormat());

        $GLOBALS['objPage'] = (object) ['outputFormat' => null];
        $this->assertSame('html5', $itemlist->getOutputFormat());
    }
}