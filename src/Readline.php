<?php
/**
 * Readline library
 *
 * @author Lee Keitel
 * @license MIT
 */
namespace Onesimus\Readline;

define('OS_WIN', PHP_OS == 'WINNT');

class Readline
{
    const READ_CONTINUE = 1;
    const READ_NO_ECHO = 2;
    const READ_BREAK = 4;

    private $oldInteraction = null;
    private $advanced = null;

    private $history = [];
    private $historySize = 1;
    private $historyCurrent = 0;

    private $buffer = '';

    private $line = '';
    private $lineLength = 0;
    private $lineCurrent = 0;

    private $prefix = '';
    private $mappings = [];

    public function __construct()
    {
        $this->advancedInteraction();

        $this->addMapping("\033[A", [$this, 'bindArrowUp']);
        $this->addMapping("\033[B", [$this, 'bindArrowDown']);
        $this->addMapping("\033[C", [$this, 'bindArrowRight']);
        $this->addMapping("\033[D", [$this, 'bindArrowLeft']);
        $this->addMapping("\010", [$this, 'bindBackspace']);
        $this->addMapping("\177", [$this, 'bindBackspace']);
        $this->addMapping("\n", [$this, 'bindNewLine']);

  //       $this->_mapping["\C-a"]   = xcallable($this, '_bindControlA');
  //       $this->_mapping["\C-b"]   = xcallable($this, '_bindControlB');
  //       $this->_mapping["\C-e"]   = xcallable($this, '_bindControlE');
  //       $this->_mapping["\C-f"]   = xcallable($this, '_bindControlF');
  //       $this->_mapping["\C-w"]   = xcallable($this, '_bindControlW');
  //       $this->_mapping["\t"]     = xcallable($this, '_bindTab');
    }

    public function __destruct()
    {
        $this->restoreInteraction();
    }

    public function advancedInteraction()
    {
        if ($this->advanced !== null) {
            return $this->advanced;
        }
        if (OS_WIN) {
            return $this->advanced = false;
        }
        $this->oldInteraction = $this->execute('stty -g');
        $this->execute('stty -echo -icanon min 1 time 0');
        return $this->advanced = true;
    }

    public function restoreInteraction()
    {
        if ($this->oldInteraction === null) {
            return;
        }
        $this->execute('stty ' . $this->oldInteraction);
        return;
    }

    public function readline($prefix = '')
    {
        if (feof(STDIN)) {
            return false;
        }

        if (OS_WIN) {
            $out = fgets(STDIN);
            if (false === $out) {
                return false;
            }
            $out = substr($out, 0, -1);
            echo $prefix, $out, "\n";
            return $out;
        }

        $this->resetLine();
        $this->setPrefix($prefix);
        $read = [STDIN];
        echo $prefix;

        while (true) {
            @stream_select($read, $write, $except, 30, 0);

            if (!$read) {
                $read = [STDIN];
                continue;
            }

            $char          = $this->_read();
            $this->buffer  = $char;
            $return        = $this->_readLine($char);

            if (($return & self::READ_NO_ECHO) === 0) {
                echo $this->buffer;
            }

            if (($return & self::READ_BREAK) !== 0) {
                break;
            }
        }

        return $this->getLine();
    }

    public function _readLine($char)
    {
        if (isset($this->mappings[$char]) &&
            is_callable($this->mappings[$char])) {
            $return = call_user_func($this->mappings[$char], $this);
        } else {
            if (isset($this->mappings[$char])) {
                $this->buffer = $this->mappings[$char];
            }

            if ($this->getLineLength() == $this->getLineCurrent()) {
                $this->appendLine($this->buffer);
                $return = self::READ_CONTINUE;
            } else {
                $this->insertLine($this->buffer);
                $tail          = mb_substr(
                    $this->getLine(),
                    $this->getLineCurrent() - 1
                );
                $this->buffer = "\033[K" . $tail . str_repeat(
                    "\033[D",
                    mb_strlen($tail) - 1
                );
                $return = self::READ_CONTINUE;
            }
        }
        return $return;
    }

    public function _read($length = 512)
    {
        return fread(STDIN, $length);
    }

    public function setPrefix($prefix = '')
    {
        $this->prefix = $prefix;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function insertLine($insert)
    {
        if ($this->lineLength == $this->lineCurrent) {
            return $this->appendLine($insert);
        }
        $this->line         = mb_substr($this->line, 0, $this->lineCurrent) .
                               $insert .
                               mb_substr($this->line, $this->lineCurrent);
        $this->lineLength   = mb_strlen($this->line);
        $this->lineCurrent += mb_strlen($insert);
        return;
    }

    public function appendLine($append)
    {
        $this->line .= $append;
        $this->lineLength = mb_strlen($this->line);
        $this->lineCurrent = $this->lineLength;
    }

    public function resetLine()
    {
        $this->line = '';
        $this->lineLength = 0;
        $this->lineCurrent = 0;
    }

    public function setLine($line)
    {
        $this->line        = $line;
        $this->lineLength  = mb_strlen($this->line);
        $this->lineCurrent = $this->lineLength;
    }

    public function setLineCurrent($current)
    {
        $this->lineCurrent = $current;
    }

    public function getLineCurrent()
    {
        return $this->lineCurrent;
    }

    public function getLineLength()
    {
        return $this->lineLength;
    }

    public function setBuffer($buffer)
    {
        $this->buffer = $buffer;
    }

    public function addMapping($key, $mapping)
    {
        if (substr($key, 0, 3) === '\e[') {
            $this->mappings["\033[" . substr($key, 3)] = $mapping;
        } elseif (substr($key, 0, 3) === '\C-') {
            $key                       = ord(strtolower(substr($key, 3))) - 96;
            $this->mappings[chr($key)] = $mapping;
        } else {
            $this->mappings[$key] = $mapping;
        }
        return;
    }

    public function addHistory($line)
    {
        if (empty($line)) {
            return;
        }

        $this->history []= $line;
        $this->historyCurrent = $this->historySize;
        $this->historySize++;
    }

    public function getHistory($id = null)
    {
        if ($id === null) {
            $id = $this->historyCurrent;
        }

        if (!isset($this->history[$id])) {
            return null;
        }

        return $this->history[$id];
    }

    public function previousHistory()
    {
        if ($this->historyCurrent <= 0) {
            return $this->getHistory(0);
        }

        return $this->getHistory(--$this->historyCurrent);
    }

    public function nextHistory()
    {
        if ($this->historySize <= $this->historyCurrent+1) {
            return $this->getLine();
        }

        return $this->getHistory(++$this->historyCurrent);
    }

    public function clearHistory()
    {
        $this->history = [];
        $this->historyCurrent = 0;
        $this->historySize = 1;
    }

    public function bindArrowUp(Readline $self)
    {
        $self->clearTerminalLine();
        $prefix = $self->getPrefix();
        $buffer = $self->previousHistory();
        $self->setBuffer("\r".$prefix.$buffer);
        $self->setLine($buffer);

        return $self::READ_CONTINUE;
    }

    public function bindArrowDown(Readline $self)
    {
        $self->clearTerminalLine();
        $prefix = $self->getPrefix();
        $self->setBuffer("\r".$prefix.$buffer = $self->nextHistory());
        $self->setLine($buffer);

        return $self::READ_CONTINUE;
    }

    public function bindArrowRight(Readline $self)
    {
        if ($self->getLineCurrent() < $self->getLineLength()) {
            $self->setLineCurrent($self->getLineCurrent()+1);
            $self->setBuffer("\033[1C");
        } else {
            $self->setBuffer('');
        }
        return $self::READ_CONTINUE;
    }

    public function bindArrowLeft(Readline $self)
    {
        if ($self->getLineCurrent() > 0) {
            $self->setLineCurrent($self->getLineCurrent()-1);
            $self->setBuffer("\033[1D");
        } else {
            $self->setBuffer('');
        }
        return $self::READ_CONTINUE;
    }

    public function bindBackspace(Readline $self)
    {
        $cursor = '';
        if ($self->getLineCurrent() > 0) {
            if ($self->getLineLength() == $current = $self->getLineCurrent()) {
                $self->setLine(mb_substr($self->getLine(), 0, -1));
            } else {
                $line    = $self->getLine();
                $current = $self->getLineCurrent();
                $tail    = mb_substr($line, $current);
                $movecursor  = $self->getLineLength() - $current;
                $cursor = "\033[{$movecursor}D";
                $self->setLine(mb_substr($line, 0, $current - 1) . $tail);
                $self->setLineCurrent($current - 1);
            }
        }

        $prefix = $self->getPrefix();
        $self->clearTerminalLine();
        $self->setBuffer("\r".$prefix.$self->getLine().$cursor);

        return $self::READ_CONTINUE;
    }

    public function bindNewLine(Readline $self)
    {
        $self->addHistory($self->getLine());
        return $self::READ_BREAK;
    }

    public static function execute($commandLine, $escape = true)
    {
        if (true === $escape) {
            $commandLine = escapeshellcmd($commandLine);
        }
        return rtrim(shell_exec($commandLine));
    }

    public function clearTerminalLine()
    {
        $length = mb_strlen($this->getPrefix())+$this->getLineLength()+1;
        echo "\r".str_repeat(' ', $length);
    }
}
