<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace KdybyTests\Translation;

use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;

class HomepagePresenter extends Presenter
{

    /**
     * @return Form
     */
    protected function createComponent(string $name): ?IComponent
    {
        $form = new Form();
        $form->addProtection('Invalid CSRF token');
        $form->addError('Nope!');
        $form->addText('a', $label = null, $cols = null, $maxLength = null);
        $form->addPassword('b', $label = null, $cols = null, $maxLength = null);
        $form->addTextArea('c', $label = null, $cols = 40, $rows = 10);
        $form->addUpload('d', $label = null)
            ->addError('Yep!');
        $form->addHidden('e', $default = null);
        $form->addCheckbox('f', $caption = null);
        $form->addRadioList('g', $label = null, $items = null);
        $form->addSelect('h', $label = null, $items = null, $size = null);
        $form->addMultiSelect('i', $label = null, $items = null, $size = null);
        $form->addSubmit('j', $caption = null);
        $form->addButton('k', $caption);
        $form->addImage('l', $src = null, $alt = null)
            ->addCondition($form::EQUAL, 1)
            ->addRule($form::FILLED, 'The image is missing!', 4);

        $form->addSubmit('send', 'Submit');
        $form->onSuccess[] = function (Form $form, $values) {
            $this->flashMessage('Entry with id %id% was saved', 'warning')
                ->parameters = ['id' => $this->getParameter('id')];

            $this->redirect('list');
        };

        return $form;
    }

}
