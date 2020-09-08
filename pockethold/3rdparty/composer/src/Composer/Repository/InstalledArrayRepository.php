<?php











namespace Composer\Repository;








class InstalledArrayRepository extends WritableArrayRepository implements InstalledRepositoryInterface
{
public function getRepoName()
{
return 'installed '.parent::getRepoName();
}




public function isFresh()
{

 
 return $this->count() === 0;
}
}
