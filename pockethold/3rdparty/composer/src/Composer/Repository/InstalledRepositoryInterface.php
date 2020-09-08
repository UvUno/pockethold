<?php











namespace Composer\Repository;








interface InstalledRepositoryInterface extends WritableRepositoryInterface
{



public function isFresh();
}
