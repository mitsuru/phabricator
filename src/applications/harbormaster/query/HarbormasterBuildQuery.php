<?php

final class HarbormasterBuildQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $buildablePHIDs;
  private $buildPlanPHIDs;

  private $needBuildPlans;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBuildablePHIDs(array $buildable_phids) {
    $this->buildablePHIDs = $buildable_phids;
    return $this;
  }

  public function withBuildPlanPHIDs(array $build_plan_phids) {
    $this->buildPlanPHIDs = $build_plan_phids;
    return $this;
  }

  public function needBuildPlans($need_plans) {
    $this->needBuildPlans = $need_plans;
    return $this;
  }

  protected function loadPage() {
    $table = new HarbormasterBuild();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $page) {
    $buildables = array();

    $buildable_phids = array_filter(mpull($page, 'getBuildablePHID'));
    if ($buildable_phids) {
      $buildables = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($buildable_phids)
        ->setParentQuery($this)
        ->execute();
      $buildables = mpull($buildables, null, 'getPHID');
    }

    foreach ($page as $key => $build) {
      $buildable_phid = $build->getBuildablePHID();
      if (empty($buildables[$buildable_phid])) {
        unset($page[$key]);
        continue;
      }
      $build->attachBuildable($buildables[$buildable_phid]);
    }

    return $page;
  }

  protected function didFilterPage(array $page) {
    if ($this->needBuildPlans) {
      $plans = array();

      $plan_phids = array_filter(mpull($page, 'getBuildPlanPHID'));
      if ($plan_phids) {
        $plans = id(new PhabricatorObjectQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($plan_phids)
          ->setParentQuery($this)
          ->execute();
        $plans = mpull($plans, null, 'getPHID');
      }

      foreach ($page as $key => $build) {
        $plan_phid = $build->getBuildPlanPHID();
        $build->attachBuildPlan(idx($plans, $plan_phid));
      }
    }

    return $page;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid in (%Ls)',
        $this->phids);
    }

    if ($this->buildablePHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'buildablePHID IN (%Ls)',
        $this->buildablePHIDs);
    }

    if ($this->buildPlanPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'buildPlanPHID IN (%Ls)',
        $this->buildPlanPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationHarbormaster';
  }

}
