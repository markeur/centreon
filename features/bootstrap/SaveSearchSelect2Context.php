<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Tester\Exception\PendingException;

/**
 * Defines application features from the specific context.
 */
class SaveSearchSelect2Context extends CentreonContext
{
    /**
     * @Given a search on a select2
     */
    public function aSearchOnASelect2()
    {
        /* Go to the page to connector configuration page */
        $this->minkContext->visit('/centreon/main.php?p=60806&o=c&id=1');

        /* Wait page loaded */
        $this->spin(
            function ($context) {
                return $context->session->getPage()->has(
                    'css',
                    'input[name="submitC"]'
                );
            },
            30
        );

        /* Add search to select2 */
        $inputField = $this->assertFind('css', 'select#command_id');

        /* Open the select2 */
        $choice = $inputField->getParent()->find('css', '.select2-selection');
        if (!$choice) {
            throw new \Exception('No select2 choice found');
        }
        $choice->press();
        $this->spin(
            function ($context) {
                return count($context->session->getPage()->findAll('css', '.select2-container--open li.select2-results__option')) >= 4;
            },
            30
        );

        $this->session->executeScript(
            'jQuery("select#command_id").parent().find(".select2-search__field").val("load");'
        );
        $this->session->wait(1000);


    }

    /**
     * @Given I close this select2
     */
    public function iCloseThisSelect2()
    {
        /* Add search to select2 */
        $inputField = $this->assertFind('css', 'select#command_id');

        /* Open the select2 */
        $choice = $inputField->getParent()->find('css', '.select2-selection');
        if (!$choice) {
            throw new \Exception('No select2 choice found');
        }

        $choice->press();
        $this->session->wait(1000);
    }

    /**
     * @When I reopen this select2
     */
    public function iReopenThisSelect2()
    {
        /* Add search to select2 */
        $inputField = $this->assertFind('css', 'select#command_id');

        /* Open the select2 */
        $choice = $inputField->getParent()->find('css', '.select2-selection');
        if (!$choice) {
            throw new \Exception('No select2 choice found');
        }
        $choice->press();
        $this->spin(
            function ($context) {
                return count($context->session->getPage()->findAll('css', '.select2-container--open li.select2-results__option')) == 4;
            },
            30
        );
    }

    /**
     * @Then the search is fill by the previous search
     */
    public function theSearchIsFillByThePreviousSearch()
    {
        /* Add search to select2 */
        $inputField = $this->assertFind('css', 'select#command_id');

        /* Open the select2 */
        $choice = $inputField->getParent()->find('css', '.select2-selection');
        if (!$choice) {
            throw new \Exception('No select2 choice found');
        }
        if ($choice->find('css', '.select2-search__field')->getValue() !== 'load') {
            throw new \Exception('The field search is not filled');
        }
    }

    /**
     * @Then the elements are filtered
     */
    public function theElementsAreFiltered()
    {
        /* Add search to select2 */
        $inputField = $this->assertFind('css', 'select#command_id');

        /* Open the select2 */
        $choice = $inputField->getParent()->find('css', '.select2-selection');
        if (!$choice) {
            throw new \Exception('No select2 choice found');
        }
        foreach ($choice->findAll('css', '.select2-results li') as $element) {
            if (stristr($element, 'load') === false) {
                throw new \Exception('An element is not filtered.');
            }
        }
    }
}