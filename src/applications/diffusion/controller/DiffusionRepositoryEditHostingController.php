<?php

final class DiffusionRepositoryEditHostingController
  extends DiffusionRepositoryEditController {

  private $serve;

  public function willProcessRequest(array $data) {
    parent::willProcessRequest($data);
    $this->serve = idx($data, 'serve');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($user)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($repository->getID()))
      ->executeOne();
    if (!$repository) {
      return new Aphront404Response();
    }

    if (!$this->serve) {
      return $this->handleHosting($repository);
    } else {
      return $this->handleProtocols($repository);
    }
  }

  public function handleHosting(PhabricatorRepository $repository) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $v_hosting = $repository->isHosted();

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');
    $next_uri = $this->getRepositoryControllerURI($repository, 'edit/serve/');

    if ($request->isFormPost()) {
      $v_hosting = $request->getBool('hosting');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_hosting = PhabricatorRepositoryTransaction::TYPE_HOSTING;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_hosting)
        ->setNewValue($v_hosting);

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($user)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($next_uri);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Hosting')));

    $title = pht('Edit Hosting (%s)', $repository->getName());

    $hosted_control = id(new AphrontFormRadioButtonControl())
        ->setName('hosting')
        ->setLabel(pht('Hosting'))
        ->addButton(
          true,
          pht('Host Repository on Phabricator'),
          pht(
            'Phabricator will host this repository. Users will be able to '.
            'push commits to Phabricator. Phabricator will not pull '.
            'changes from elsewhere.'))
        ->addButton(
          false,
          pht('Host Repository Elsewhere'),
          pht(
            'Phabricator will pull updates to this repository from a master '.
            'repository elsewhere (for example, on GitHub or Bitbucket). '.
            'Users will not be able to push commits to this repository.'))
        ->setValue($v_hosting);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions(
        pht(
          'NOTE: Hosting is extremely new and barely works! Use it at '.
          'your own risk.'.
          "\n\n".
          'Phabricator can host repositories, or it can track repositories '.
          'hosted elsewhere (like on GitHub or Bitbucket).'))
      ->appendChild($hosted_control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save and Continue'))
          ->addCancelButton($edit_uri));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  public function handleProtocols(PhabricatorRepository $repository) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $v_http_mode = $repository->getServeOverHTTP();
    $v_ssh_mode = $repository->getServeOverSSH();

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');
    $prev_uri = $this->getRepositoryControllerURI($repository, 'edit/hosting/');

    if ($request->isFormPost()) {
      $v_http_mode = $request->getStr('http');
      $v_ssh_mode = $request->getStr('ssh');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_http = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP;
      $type_ssh = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_http)
        ->setNewValue($v_http_mode);

      $xactions[] = id(clone $template)
        ->setTransactionType($type_ssh)
        ->setNewValue($v_ssh_mode);

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($user)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Protocols')));

    $title = pht('Edit Protocols (%s)', $repository->getName());


    if ($repository->isHosted()) {
      $rw_message = pht(
        'Phabricator will serve a read-write copy of this repository');
    } else {
      $rw_message = pht(
        'This repository is hosted elsewhere, so Phabricator can not perform '.
        'writes.');
    }

    $ssh_control =
      id(new AphrontFormRadioButtonControl())
        ->setName('ssh')
        ->setLabel(pht('SSH'))
        ->setValue($v_ssh_mode)
        ->addButton(
          PhabricatorRepository::SERVE_OFF,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_OFF),
          pht('Phabricator will not serve this repository.'))
        ->addButton(
          PhabricatorRepository::SERVE_READONLY,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_READONLY),
          pht('Phabricator will serve a read-only copy of this repository.'))
        ->addButton(
          PhabricatorRepository::SERVE_READWRITE,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_READWRITE),
          $rw_message,
          $repository->isHosted() ? null : 'disabled',
          $repository->isHosted() ? null : true);

    $http_control =
      id(new AphrontFormRadioButtonControl())
        ->setName('http')
        ->setLabel(pht('HTTP'))
        ->setValue($v_http_mode)
        ->addButton(
          PhabricatorRepository::SERVE_OFF,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_OFF),
          pht('Phabricator will not serve this repository.'))
        ->addButton(
          PhabricatorRepository::SERVE_READONLY,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_READONLY),
          pht('Phabricator will serve a read-only copy of this repository.'))
        ->addButton(
          PhabricatorRepository::SERVE_READWRITE,
          PhabricatorRepository::getProtocolAvailabilityName(
            PhabricatorRepository::SERVE_READWRITE),
          $rw_message,
          $repository->isHosted() ? null : 'disabled',
          $repository->isHosted() ? null : true);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions(
        pht(
          'Phabricator can serve repositories over various protocols. You can '.
          'configure server protocols here.'))
      ->appendChild($ssh_control)
      ->appendChild($http_control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Changes'))
          ->addCancelButton($prev_uri, pht('Back')));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
