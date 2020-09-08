<?php











namespace Composer\Package;

use Composer\Repository\RepositoryInterface;






interface PackageInterface
{
const DISPLAY_SOURCE_REF_IF_DEV = 0;
const DISPLAY_SOURCE_REF = 1;
const DISPLAY_DIST_REF = 2;






public function getName();






public function getPrettyName();











public function getNames($provides = true);






public function setId($id);






public function getId();






public function isDev();






public function getType();






public function getTargetDir();






public function getExtra();






public function setInstallationSource($type);






public function getInstallationSource();






public function getSourceType();






public function getSourceUrl();






public function getSourceUrls();






public function getSourceReference();






public function getSourceMirrors();






public function getDistType();






public function getDistUrl();






public function getDistUrls();






public function getDistReference();






public function getDistSha1Checksum();






public function getDistMirrors();






public function getVersion();






public function getPrettyVersion();












public function getFullPrettyVersion($truncate = true, $displayMode = self::DISPLAY_SOURCE_REF_IF_DEV);






public function getReleaseDate();






public function getStability();







public function getRequires();







public function getConflicts();







public function getProvides();







public function getReplaces();







public function getDevRequires();








public function getSuggests();












public function getAutoload();












public function getDevAutoload();







public function getIncludePaths();






public function setRepository(RepositoryInterface $repository);






public function getRepository();






public function getBinaries();






public function getUniqueName();






public function getNotificationUrl();






public function __toString();






public function getPrettyString();






public function getArchiveName();






public function getArchiveExcludes();




public function isDefaultBranch();






public function getTransportOptions();






public function setSourceReference($reference);






public function setDistUrl($url);






public function setDistType($type);






public function setDistReference($reference);








public function setSourceDistReferences($reference);
}
