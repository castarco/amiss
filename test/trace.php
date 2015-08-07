<?php
$basePath = __DIR__.'/../';
chdir($basePath);
require $basePath.'/vendor/autoload.php';
goto defs;
script:

$cli = new \League\CLImate\CLImate;

const PARAMS_FULL = 4;
const PARAMS_TYPE = 2;

$paramsMode = PARAMS_TYPE;
$paramsStripLengths = true;

$id = uniqid('amiss-', true);
$cmd =  "php -d xdebug.auto_trace=1 " .
        "-d xdebug.collect_params=$paramsMode " .
        "-d xdebug.trace_format=1 " .
        "-d xdebug.trace_output_dir=build " .
        "-d xdebug.trace_output_name=$id " .
        "-d xdebug.var_display_max_depth=3 " .
        "test/run.php";

$traceFile = $basePath.'/build/'.$id.'.xt';
$traceFile = $basePath.'/build/amiss-1438925638_159254.xt';
$traceIndex = $traceFile.'.idx';

$searchFunctions = [
    'Amiss\Sql\Manager->assignRelated',
    'Amiss\Sql\Manager->count',
    'Amiss\Sql\Manager->createKeyCriteria',
    'Amiss\Sql\Manager->delete',
    'Amiss\Sql\Manager->deleteById',
    'Amiss\Sql\Manager->deleteTable',
    'Amiss\Sql\Manager->exists',
    'Amiss\Sql\Manager->get',
    'Amiss\Sql\Manager->getById',
    'Amiss\Sql\Manager->getList',
    'Amiss\Sql\Manager->getRelated',
    'Amiss\Sql\Manager->getRelator',
    'Amiss\Sql\Manager->groupBy',
    'Amiss\Sql\Manager->indexBy',
    'Amiss\Sql\Manager->insert',
    'Amiss\Sql\Manager->insertTable',
    'Amiss\Sql\Manager->populateObjectsWithRelated',
    'Amiss\Sql\Manager->save',
    'Amiss\Sql\Manager->selectList',
    'Amiss\Sql\Manager->shouldInsert',
    'Amiss\Sql\Manager->update',
    'Amiss\Sql\Manager->updateTable',
];

$searchFunctionIndex = [];
foreach ($searchFunctions as $fn) {
    $searchFunctionIndex[$fn] = true;
}

$functions = [];
foreach (xdebug_trace_pop_iter(xdebug_trace_fmt1_iter($traceFile)) as list ($call, $trace)) {
    if (isset($searchFunctionIndex[$call->entry->function])) {
        $functions[$call->entry->function][] = [$call, $trace];
    }
}

$widthLimit = 60;
$showTrace = false;

ksort($functions);
foreach ($functions as $name=>$calls) {
    if ($showTrace) {
        $callCount = count($calls);

        foreach ($calls as $callIdx => list($call, $trace)) {
            // $cli->green()->bold()->out(sprintf("%d/%d: %s", $callIdx + 1, $callCount, $call->entry->function));

            write_argv($cli, $name, $call->entry->argv, $paramsMode, $widthLimit);
            $cli->br();

            $cli->bold()->yellow()->out("Trace:");
            foreach (array_reverse($trace) as $frame) {
                $cli->out("{$frame->function}() - {$frame->file}:{$frame->line}");
            }
            $cli->br();
        }
    }
    else {
        $callCounts = [];
        foreach ($calls as $callIdx => $call) {
            if ($paramsStripLengths && $paramsMode == PARAMS_TYPE) {
                foreach ($call[0]->entry->argv as &$arg) {
                    $arg = preg_replace('/^(array|string)\(\d+\)$/', '$1', $arg);
                }
                unset($arg);
            }

            $argHash = serialize($call[0]->entry->argv);
            if (!isset($callCounts[$argHash])) {
                $callCounts[$argHash] = ['count'=>1, 'call'=>$call];
            } else {
                ++$callCounts[$argHash]['count'];
            }
        }

        if ($paramsMode == PARAMS_TYPE) {
            $cli->lightGreen()->bold()->out($name);
            $rows = [['<blue>calls</blue>', '<blue>args...</blue>']];
            $maxRows = 1;
            foreach ($callCounts as $callCount) {
                list ($call, $trace) = $callCount['call'];

                $row = $call->entry->argv;
                array_unshift($row, "x{$callCount['count']}");
                $maxRows = max($call->entry->argc + 1, $maxRows);
                $rows[] = $row;
            }
            
            $cli->columns($rows, $maxRows);
            $cli->br();
        }
    }
}

return;
defs:

function write_argv($cli, $name, $argv, $mode, $widthLimit=null)
{
    if ($mode == PARAMS_FULL) {
        $cli->lightGreen()->bold()->out($name);
        
        $idx = 1;
        foreach ($argv as $arg) {
            if ($widthLimit) {
                if (strlen($arg) > $widthLimit) {
                    $arg = substr($arg, 0, $widthLimit).'...';
                }
            }
            $cli->out("<bold>".($idx)."</bold>. ".$arg);
            $idx++;
        }
    }
}

function xdebug_trace_pop_iter($recordIter)
{
    $stack = [];
    $entryStack = [];
    $lastLevel = 0;
    foreach ($recordIter as $record) {
        if ($lastLevel && $lastLevel == $record->level && $record instanceof TraceEntry) {
            $ret = array_pop($stack);
            array_pop($entryStack);
            yield [$ret, $entryStack];
        }
        elseif ($record->level < $lastLevel && $record instanceof TraceExit) {
            $ret = array_pop($stack);
            array_pop($entryStack);
            yield [$ret, $entryStack];
        }

        if ($record instanceof TraceEntry) {
            $stack[$record->level] = (object)['entry' => $record, 'exit' => null, 'return' => null];
            $entryStack[] = $record;
        }
        elseif ($record instanceof TraceExit) {
            $stack[$record->level]->exit = $record;
        }
        elseif ($record instanceof TraceReturn) {
            $stack[$record->level]->return = $record;
        }

        $lastLevel = $record->level;
    }

    if (count($stack) > 1) {
        throw new \Exception();
    }
    elseif ($stack) {
        yield [current($stack), []];
    }
}

function xdebug_trace_fmt1_iter($traceFile, $query=[])
{
    $defaults = [
        'limit'      => null,
        'functions'  => null,
        'entryTypes' => [ TraceEntry::class , TraceExit::class , TraceReturn::class ],
    ];
    $query = array_merge($defaults, $query);

    $limit = $query['limit'];

    foreach ((array)$query['entryTypes'] as $t) { $entryTypeIndex[$t] = true; }

    $XT_IN_HDR = 1;
    $XT_IN_VER = 2;
    $XT_IN_V4  = 3;
    $XT_IN_V4_FOOTER = 4;

    if (!is_resource($traceFile)) {
        $h = fopen($traceFile, 'r');
        if (!$h) {
            throw new \UnexpectedValueException();
        }
    } else {
        $h = $traceFile;
    }

    $state = $XT_IN_HDR;

    $meta = [];
    $lineNum = 0;
    $record = 0;

    while (!feof($h)) {
        $lineNum++;
        $line = rtrim(fgets($h));

    parse_line:
        state_hdr: if ($state === $XT_IN_HDR) {
            if (strpos($line, 'TRACE START') === 0) {
                $state = $XT_IN_V4;
                goto next_record;
            }
            else {
                $parts = preg_split('/:\s*/', $line, 2);
                if (!isset($parts[1])) {
                    throw new \UnexpectedValueException("Invalid header at line $lineNum");
                }
                $meta[$parts[0]] = $parts[1];
            }
        }

        state_ver: if ($state === $XT_IN_VER) {
            if (!isset($meta['File format']) || $meta['File format'] != 4) {
                throw new \UnexpectedValueException(
                    "Only supports file format 4, found ".(isset($meta['File format']) ? $meta['File format'] : '(null)')
                );
            }
            $state = $XT_IN_V4;
        }

        state_trace_v4: if ($state === $XT_IN_V4) {
            $parts = explode("\t", $line);
            ++$record;
            if ($limit && $record >= $limit) {
                goto done;
            }

            switch ($parts[TraceRecord::FMT1_TYPE]) {
            case TraceEntry::TYPE_CODE:
                if (isset($entryTypeIndex[TraceEntry::class])) {
                    $yield = true;
                    if (isset($query['functions'])) {
                        $yield = TraceEntry::allowFormat1($parts, $query);
                    }
                    if ($yield) {
                        yield TraceEntry::fromFormat1($parts);
                    }
                }
            break;

            case TraceExit::TYPE_CODE:
                if (isset($entryTypeIndex[TraceExit::class])) {
                    yield TraceExit::fromFormat1($parts);
                }
            break;

            case TraceReturn::TYPE_CODE:
                if (isset($entryTypeIndex[TraceReturn::class])) {
                    yield TraceReturn::fromFormat1($parts);
                }
            break;
            
            case '':
                // strange footer line with no type, contains values at col 4 and 5
                if (count($parts) === 5) {
                    $state = $XT_IN_V4_FOOTER;
                    goto next_record;
                }
                elseif (strpos($line, 'TRACE END') === 0) {
                    $state = $XT_IN_V4_FOOTER;
                    goto parse_line;
                }

            default:
                throw new \UnexpectedValueException("Unexpected record type ".$parts[TraceRecord::FMT1_TYPE]." at line $lineNum");
            }
        }

        state_trace_v4_footer: if ($state === $XT_IN_V4_FOOTER) {
            if ($line && strpos($line, 'TRACE END') !== 0) {
                throw new \UnexpectedValueException();
            }
        }

    next_record:
    }

done:
}

abstract class TraceRecord
{
    const FMT1_LEVEL        = 0;
    const FMT1_FUNCTION_NUM = 1;
    const FMT1_TYPE         = 2;
    const FMT1_TIME_INDEX   = 3;
    const FMT1_MEM_USAGE    = 4;

    public $level;
    public $functionNum;
}

class TraceEntry extends TraceRecord
{
    const TYPE_CODE = "0";

    const FMT1_ARGC = 10;
    const FMT1_FUNCTION = 5;

    public $timeIndex;
    public $memUsage;
    public $function;
    public $isUserDefined;
    public $requreFile;
    public $file;
    public $line;
    public $argc;
    public $argv = [];
    public $ellipsisArg;

    static function allowFormat1($parts, $query)
    {
        $functionsMatched = true;

        if ($query['functions']) {
            $functionsMatched = false;
            $function = $parts[static::FMT1_FUNCTION];
            foreach ($query['functions'] as $funcPattern) {
                if (preg_match($funcPattern, $function)) {
                    $functionsMatched = true;
                    break;
                }
            }
        }

        return $functionsMatched;
    }

    static function fromFormat1($parts)
    {
        if ($parts[static::FMT1_TYPE] !== static::TYPE_CODE) {
            throw new \InvalidArgumentException("Invalid type ".$parts[static::FMT1_TYPE]);
        }
        if (!isset($parts[static::FMT1_ARGC])) {
            throw new \InvalidArgumentException();
        }
        $c = new static;
        $c->level = $parts[static::FMT1_LEVEL];
        $c->functionNum = $parts[static::FMT1_FUNCTION_NUM];
        $c->timeIndex = $parts[static::FMT1_TIME_INDEX];
        $c->memUsage = $parts[static::FMT1_MEM_USAGE];

        $c->function = $parts[static::FMT1_FUNCTION];
        if ($parts[6] === "1") {
            $c->isUserDefined = true;
        } elseif ($parts[6] === "0") {
            $c->isUserDefined = false;
        } else {
            throw new \InvalidArgumentException();
        }

        $c->requireFile = $parts[7];
        $c->file = $parts[8];
        $c->line = $parts[9];
        $c->argc = $parts[static::FMT1_ARGC];

        if ($c->argc) {
            for ($i = 0, $a = static::FMT1_ARGC + 1; $i < $c->argc; $i++, $a++) {
                if (isset($parts[$a])) {
                    $arg = $parts[$a];
                    if ($arg === '...') {
                        $c->ellipsisArg = $i;
                        $c->argc -= 1;
                    } else {
                        $c->argv[] = $parts[$a];
                    }
                }
            }
        }
        return $c;
    }
}

class TraceExit extends TraceRecord
{
    const TYPE_CODE = "1";

    public $timeIndex;
    public $memUsage;

    static function fromFormat1($parts)
    {
        if ($parts[static::FMT1_TYPE] !== static::TYPE_CODE) {
            throw new \InvalidArgumentException("Invalid type ".$parts[static::FMT1_TYPE]);
        }
        if (!isset($parts[static::FMT1_MEM_USAGE])) {
            throw new \InvalidArgumentException();
        }
        $c = new static;
        $c->level = $parts[static::FMT1_LEVEL];
        $c->functionNum = $parts[static::FMT1_FUNCTION_NUM];
        $c->timeIndex = $parts[static::FMT1_TIME_INDEX];
        $c->memUsage = $parts[static::FMT1_MEM_USAGE];
        return $c;
    }
}

class TraceReturn extends TraceRecord
{
    const TYPE_CODE = 'R';

    public $returnValue;

    static function fromFormat1(array $parts)
    {
        if ($parts[static::FMT1_TYPE] !== static::TYPE_CODE) {
            throw new \InvalidArgumentException("Invalid type ".$parts[static::FMT1_TYPE]);
        }
        if (!isset($parts[5])) {
            throw new \InvalidArgumentException();
        }
        $c = new static;
        $c->level = $parts[static::FMT1_LEVEL];
        $c->functionNum = $parts[static::FMT1_FUNCTION_NUM];
        $c->returnValue = $parts[5];
        return $c;
    }
}

goto script;

