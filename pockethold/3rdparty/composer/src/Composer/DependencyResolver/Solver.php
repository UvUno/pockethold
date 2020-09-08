<?php











namespace Composer\DependencyResolver;

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\PlatformRepository;
use Composer\Semver\Constraint\MultiConstraint;




class Solver
{
const BRANCH_LITERALS = 0;
const BRANCH_LEVEL = 1;


protected $policy;

protected $pool;


protected $rules;


protected $watchGraph;

protected $decisions;

protected $fixedMap;


protected $propagateIndex;

protected $branches = array();

protected $problems = array();

protected $learnedPool = array();

protected $learnedWhy = array();


public $testFlagLearnedPositiveLiteral = false;


protected $io;






public function __construct(PolicyInterface $policy, Pool $pool, IOInterface $io)
{
$this->io = $io;
$this->policy = $policy;
$this->pool = $pool;
}




public function getRuleSetSize()
{
return \count($this->rules);
}

public function getPool()
{
return $this->pool;
}



private function makeAssertionRuleDecisions()
{
$decisionStart = \count($this->decisions) - 1;

$rulesCount = \count($this->rules);
for ($ruleIndex = 0; $ruleIndex < $rulesCount; $ruleIndex++) {
$rule = $this->rules->ruleById[$ruleIndex];

if (!$rule->isAssertion() || $rule->isDisabled()) {
continue;
}

$literals = $rule->getLiterals();
$literal = $literals[0];

if (!$this->decisions->decided($literal)) {
$this->decisions->decide($literal, 1, $rule);
continue;
}

if ($this->decisions->satisfy($literal)) {
continue;
}


 if (RuleSet::TYPE_LEARNED === $rule->getType()) {
$rule->disable();
continue;
}

$conflict = $this->decisions->decisionRule($literal);

if ($conflict && RuleSet::TYPE_PACKAGE === $conflict->getType()) {
$problem = new Problem();

$problem->addRule($rule);
$problem->addRule($conflict);
$rule->disable();
$this->problems[] = $problem;
continue;
}


 $problem = new Problem();
$problem->addRule($rule);
$problem->addRule($conflict);


 
 foreach ($this->rules->getIteratorFor(RuleSet::TYPE_REQUEST) as $assertRule) {
if ($assertRule->isDisabled() || !$assertRule->isAssertion()) {
continue;
}

$assertRuleLiterals = $assertRule->getLiterals();
$assertRuleLiteral = $assertRuleLiterals[0];

if (abs($literal) !== abs($assertRuleLiteral)) {
continue;
}
$problem->addRule($assertRule);
$assertRule->disable();
}
$this->problems[] = $problem;

$this->decisions->resetToOffset($decisionStart);
$ruleIndex = -1;
}
}

protected function setupFixedMap(Request $request)
{
$this->fixedMap = array();
foreach ($request->getFixedPackages() as $package) {
$this->fixedMap[$package->id] = $package;
}
}





protected function checkForRootRequireProblems(Request $request, $ignorePlatformReqs)
{
foreach ($request->getRequires() as $packageName => $constraint) {
if ((true === $ignorePlatformReqs || (is_array($ignorePlatformReqs) && in_array($packageName, $ignorePlatformReqs, true))) && preg_match(PlatformRepository::PLATFORM_PACKAGE_REGEX, $packageName)) {
continue;
}

if (!$this->pool->whatProvides($packageName, $constraint)) {
$problem = new Problem();
$problem->addRule(new GenericRule(array(), Rule::RULE_ROOT_REQUIRE, array('packageName' => $packageName, 'constraint' => $constraint)));
$this->problems[] = $problem;
}
}
}






public function solve(Request $request, $ignorePlatformReqs = false)
{
$this->setupFixedMap($request);

$this->io->writeError('Generating rules', true, IOInterface::DEBUG);
$ruleSetGenerator = new RuleSetGenerator($this->policy, $this->pool);
$this->rules = $ruleSetGenerator->getRulesFor($request, $ignorePlatformReqs);
unset($ruleSetGenerator);
$this->checkForRootRequireProblems($request, $ignorePlatformReqs);
$this->decisions = new Decisions($this->pool);
$this->watchGraph = new RuleWatchGraph;

foreach ($this->rules as $rule) {
$this->watchGraph->insert(new RuleWatchNode($rule));
}


$this->makeAssertionRuleDecisions();

$this->io->writeError('Resolving dependencies through SAT', true, IOInterface::DEBUG);
$before = microtime(true);
$this->runSat();
$this->io->writeError('', true, IOInterface::DEBUG);
$this->io->writeError(sprintf('Dependency resolution completed in %.3f seconds', microtime(true) - $before), true, IOInterface::VERBOSE);

if ($this->problems) {
throw new SolverProblemsException($this->problems, $this->learnedPool);
}

return new LockTransaction($this->pool, $request->getPresentMap(), $request->getUnlockableMap(), $this->decisions);
}










protected function propagate($level)
{
while ($this->decisions->validOffset($this->propagateIndex)) {
$decision = $this->decisions->atOffset($this->propagateIndex);

$conflict = $this->watchGraph->propagateLiteral(
$decision[Decisions::DECISION_LITERAL],
$level,
$this->decisions
);

$this->propagateIndex++;

if ($conflict) {
return $conflict;
}
}

return null;
}






private function revert($level)
{
while (!$this->decisions->isEmpty()) {
$literal = $this->decisions->lastLiteral();

if ($this->decisions->undecided($literal)) {
break;
}

$decisionLevel = $this->decisions->decisionLevel($literal);

if ($decisionLevel <= $level) {
break;
}

$this->decisions->revertLast();
$this->propagateIndex = \count($this->decisions);
}

while (!empty($this->branches) && $this->branches[\count($this->branches) - 1][self::BRANCH_LEVEL] >= $level) {
array_pop($this->branches);
}
}



















private function setPropagateLearn($level, $literal, Rule $rule)
{
$level++;

$this->decisions->decide($literal, $level, $rule);

while (true) {
$rule = $this->propagate($level);

if (!$rule) {
break;
}

if ($level == 1) {
return $this->analyzeUnsolvable($rule);
}


 list($learnLiteral, $newLevel, $newRule, $why) = $this->analyze($level, $rule);

if ($newLevel <= 0 || $newLevel >= $level) {
throw new SolverBugException(
"Trying to revert to invalid level ".(int) $newLevel." from level ".(int) $level."."
);
} elseif (!$newRule) {
throw new SolverBugException(
"No rule was learned from analyzing $rule at level $level."
);
}

$level = $newLevel;

$this->revert($level);

$this->rules->add($newRule, RuleSet::TYPE_LEARNED);

$this->learnedWhy[spl_object_hash($newRule)] = $why;

$ruleNode = new RuleWatchNode($newRule);
$ruleNode->watch2OnHighest($this->decisions);
$this->watchGraph->insert($ruleNode);

$this->decisions->decide($learnLiteral, $level, $newRule);
}

return $level;
}







private function selectAndInstall($level, array $decisionQueue, Rule $rule)
{

 $literals = $this->policy->selectPreferredPackages($this->pool, $decisionQueue, $rule->getRequiredPackage());

$selectedLiteral = array_shift($literals);


 if (\count($literals)) {
$this->branches[] = array($literals, $level);
}

return $this->setPropagateLearn($level, $selectedLiteral, $rule);
}






protected function analyze($level, Rule $rule)
{
$analyzedRule = $rule;
$ruleLevel = 1;
$num = 0;
$l1num = 0;
$seen = array();
$learnedLiterals = array(null);

$decisionId = \count($this->decisions);

$this->learnedPool[] = array();

while (true) {
$this->learnedPool[\count($this->learnedPool) - 1][] = $rule;

foreach ($rule->getLiterals() as $literal) {

 if ($rule instanceof MultiConflictRule && !$this->decisions->decided($literal)) {
continue;
}


 if ($this->decisions->satisfy($literal)) {
continue;
}

if (isset($seen[abs($literal)])) {
continue;
}
$seen[abs($literal)] = true;

$l = $this->decisions->decisionLevel($literal);

if (1 === $l) {
$l1num++;
} elseif ($level === $l) {
$num++;
} else {

 $learnedLiterals[] = $literal;

if ($l > $ruleLevel) {
$ruleLevel = $l;
}
}
}
unset($literal);

$l1retry = true;
while ($l1retry) {
$l1retry = false;

if (!$num && !--$l1num) {

 break 2;
}

while (true) {
if ($decisionId <= 0) {
throw new SolverBugException(
"Reached invalid decision id $decisionId while looking through $rule for a literal present in the analyzed rule $analyzedRule."
);
}

$decisionId--;

$decision = $this->decisions->atOffset($decisionId);
$literal = $decision[Decisions::DECISION_LITERAL];

if (isset($seen[abs($literal)])) {
break;
}
}

unset($seen[abs($literal)]);

if ($num && 0 === --$num) {
if ($literal < 0) {
$this->testFlagLearnedPositiveLiteral = true;
}
$learnedLiterals[0] = -$literal;

if (!$l1num) {
break 2;
}

foreach ($learnedLiterals as $i => $learnedLiteral) {
if ($i !== 0) {
unset($seen[abs($learnedLiteral)]);
}
}

 $l1num++;
$l1retry = true;
}

$decision = $this->decisions->atOffset($decisionId);
$rule = $decision[Decisions::DECISION_REASON];

if ($rule instanceof MultiConflictRule) {

 foreach ($rule->getLiterals() as $literal) {
if (!isset($seen[abs($literal)]) && $this->decisions->satisfy(-$literal)) {
$this->learnedPool[\count($this->learnedPool) - 1][] = $rule;
$l = $this->decisions->decisionLevel($literal);
if (1 === $l) {
$l1num++;
} elseif ($level === $l) {
$num++;
} else {

 $learnedLiterals[] = $literal;

if ($l > $ruleLevel) {
$ruleLevel = $l;
}
}
$seen[abs($literal)] = true;
break;
}
}

$l1retry = true;
}
}

$decision = $this->decisions->atOffset($decisionId);
$rule = $decision[Decisions::DECISION_REASON];
}

$why = \count($this->learnedPool) - 1;

if (!$learnedLiterals[0]) {
throw new SolverBugException(
"Did not find a learnable literal in analyzed rule $analyzedRule."
);
}

$newRule = new GenericRule($learnedLiterals, Rule::RULE_LEARNED, $why);

return array($learnedLiterals[0], $ruleLevel, $newRule, $why);
}





private function analyzeUnsolvableRule(Problem $problem, Rule $conflictRule)
{
if ($conflictRule->getType() == RuleSet::TYPE_LEARNED) {
$why = spl_object_hash($conflictRule);
$learnedWhy = $this->learnedWhy[$why];
$problemRules = $this->learnedPool[$learnedWhy];

foreach ($problemRules as $problemRule) {
$this->analyzeUnsolvableRule($problem, $problemRule);
}

return;
}

if ($conflictRule->getType() == RuleSet::TYPE_PACKAGE) {

 return;
}

$problem->nextSection();
$problem->addRule($conflictRule);
}





private function analyzeUnsolvable(Rule $conflictRule)
{
$problem = new Problem();
$problem->addRule($conflictRule);

$this->analyzeUnsolvableRule($problem, $conflictRule);

$this->problems[] = $problem;

$seen = array();
$literals = $conflictRule->getLiterals();

foreach ($literals as $literal) {

 if ($this->decisions->satisfy($literal)) {
continue;
}
$seen[abs($literal)] = true;
}

foreach ($this->decisions as $decision) {
$literal = $decision[Decisions::DECISION_LITERAL];


 if (!isset($seen[abs($literal)])) {
continue;
}

$why = $decision[Decisions::DECISION_REASON];

$problem->addRule($why);
$this->analyzeUnsolvableRule($problem, $why);

$literals = $why->getLiterals();

foreach ($literals as $literal) {

 if ($this->decisions->satisfy($literal)) {
continue;
}
$seen[abs($literal)] = true;
}
}

return 0;
}

private function resetSolver()
{
$this->decisions->reset();

$this->propagateIndex = 0;
$this->branches = array();

$this->enableDisableLearnedRules();
$this->makeAssertionRuleDecisions();
}








private function enableDisableLearnedRules()
{
foreach ($this->rules->getIteratorFor(RuleSet::TYPE_LEARNED) as $rule) {
$why = $this->learnedWhy[spl_object_hash($rule)];
$problemRules = $this->learnedPool[$why];

$foundDisabled = false;
foreach ($problemRules as $problemRule) {
if ($problemRule->isDisabled()) {
$foundDisabled = true;
break;
}
}

if ($foundDisabled && $rule->isEnabled()) {
$rule->disable();
} elseif (!$foundDisabled && $rule->isDisabled()) {
$rule->enable();
}
}
}

private function runSat()
{
$this->propagateIndex = 0;











$decisionQueue = array();
$decisionSupplementQueue = array();

$level = 1;
$systemLevel = $level + 1;

while (true) {
if (1 === $level) {
$conflictRule = $this->propagate($level);
if (null !== $conflictRule) {
if ($this->analyzeUnsolvable($conflictRule)) {
continue;
}

return;
}
}


 if ($level < $systemLevel) {
$iterator = $this->rules->getIteratorFor(RuleSet::TYPE_REQUEST);
foreach ($iterator as $rule) {
if ($rule->isEnabled()) {
$decisionQueue = array();
$noneSatisfied = true;

foreach ($rule->getLiterals() as $literal) {
if ($this->decisions->satisfy($literal)) {
$noneSatisfied = false;
break;
}
if ($literal > 0 && $this->decisions->undecided($literal)) {
$decisionQueue[] = $literal;
}
}

if ($noneSatisfied && \count($decisionQueue)) {

 $prunedQueue = array();
foreach ($decisionQueue as $literal) {
if (isset($this->fixedMap[abs($literal)])) {
$prunedQueue[] = $literal;
}
}
if (!empty($prunedQueue)) {
$decisionQueue = $prunedQueue;
}
}

if ($noneSatisfied && \count($decisionQueue)) {
$oLevel = $level;
$level = $this->selectAndInstall($level, $decisionQueue, $rule);

if (0 === $level) {
return;
}
if ($level <= $oLevel) {
break;
}
}
}
}

$systemLevel = $level + 1;


 $iterator->next();
if ($iterator->valid()) {
continue;
}
}

if ($level < $systemLevel) {
$systemLevel = $level;
}

$rulesCount = \count($this->rules);
$pass = 1;

$this->io->writeError('Looking at all rules.', true, IOInterface::DEBUG);
for ($i = 0, $n = 0; $n < $rulesCount; $i++, $n++) {
if ($i == $rulesCount) {
if (1 === $pass) {
$this->io->writeError("Something's changed, looking at all rules again (pass #$pass)", false, IOInterface::DEBUG);
} else {
$this->io->overwriteError("Something's changed, looking at all rules again (pass #$pass)", false, null, IOInterface::DEBUG);
}

$i = 0;
$pass++;
}

$rule = $this->rules->ruleById[$i];
$literals = $rule->getLiterals();

if ($rule->isDisabled()) {
continue;
}

$decisionQueue = array();


 
 
 
 
 
 foreach ($literals as $literal) {
if ($literal <= 0) {
if (!$this->decisions->decidedInstall($literal)) {
continue 2; 
 }
} else {
if ($this->decisions->decidedInstall($literal)) {
continue 2; 
 }
if ($this->decisions->undecided($literal)) {
$decisionQueue[] = $literal;
}
}
}


 if (\count($decisionQueue) < 2) {
continue;
}

$level = $this->selectAndInstall($level, $decisionQueue, $rule);

if (0 === $level) {
return;
}


 $rulesCount = \count($this->rules);
$n = -1;
}

if ($level < $systemLevel) {
continue;
}


 if (\count($this->branches)) {
$lastLiteral = null;
$lastLevel = null;
$lastBranchIndex = 0;
$lastBranchOffset = 0;

for ($i = \count($this->branches) - 1; $i >= 0; $i--) {
list($literals, $l) = $this->branches[$i];

foreach ($literals as $offset => $literal) {
if ($literal && $literal > 0 && $this->decisions->decisionLevel($literal) > $l + 1) {
$lastLiteral = $literal;
$lastBranchIndex = $i;
$lastBranchOffset = $offset;
$lastLevel = $l;
}
}
}

if ($lastLiteral) {
unset($this->branches[$lastBranchIndex][self::BRANCH_LITERALS][$lastBranchOffset]);

$level = $lastLevel;
$this->revert($level);

$why = $this->decisions->lastReason();

$level = $this->setPropagateLearn($level, $lastLiteral, $why);

if ($level == 0) {
return;
}

continue;
}
}

break;
}
}
}
