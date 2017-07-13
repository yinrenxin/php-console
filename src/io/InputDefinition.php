<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/6/30
 * Time: 下午8:14
 */

namespace inhere\console\io;

/**
 * Class InputDefinition
 * @package inhere\console\io
 * @form \Symfony\Component\Console\Input\InputDefinition
 */
class InputDefinition
{
    private $example;
    private $description;

    /**
     * @var array[]
     */
    private $arguments;
    private $requiredCount = 0;
    private $hasAnArrayArgument = false;
    private $hasOptional = false;

    /**
     * @var array[]
     */
    private $options;

    /**
     * @var array
     */
    private $shortcuts;

    public static function make(array $arguments = [], array $options = [])
    {
        return new self($arguments, $options);
    }

    /**
     * Constructor.
     *
     * @param array $arguments
     * @param array $options
     */
    public function __construct(array $arguments = [], array $options = [])
    {
        $this->setArguments($arguments);
        $this->setOptions($options);
    }

    /**
     * @param array $arguments
     * @return InputDefinition
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = [];

        return $this->addArguments($arguments);
    }

    /**
     * @param array $arguments
     * @return $this
     */
    public function addArguments(array $arguments)
    {
        $def = [
            'mode' => null,
            'description' => '',
            'default' => null,
        ];

        foreach ($arguments as $name => $arg) {
            $arg = array_merge($def, $arg);
            $this->addArgument($name, $arg['mode'], $arg['description'], $arg['default']);
        }

        return $this;
    }

    /**
     * Adds an argument.
     *
     * @param string $name The argument name
     * @param int $mode The argument mode: InputArgument::REQUIRED or InputArgument::OPTIONAL
     * @param string $description A description text
     * @param mixed $default The default value (for InputArgument::OPTIONAL mode only)
     * @return $this
     * @throws \LogicException
     */
    public function addArgument($name, $mode = null, $description = '', $default = null)
    {
        if (null === $mode) {
            $mode = Input::OPT_OPTIONAL;
        } elseif (!is_int($mode) || $mode > 7 || $mode < 1) {
            throw new \InvalidArgumentException(sprintf('Argument mode "%s" is not valid.', $mode));
        }

        if (isset($this->arguments[$name])) {
            throw new \LogicException(sprintf('An argument with name "%s" already exists.', $name));
        }

        if ($this->hasAnArrayArgument) {
            throw new \LogicException('Cannot add an argument after an array argument.');
        }

        if (($required = $mode === Input::ARG_REQUIRED) && $this->hasOptional) {
            throw new \LogicException('Cannot add a required argument after an optional one.');
        }

        if ($isArray = $mode === Input::ARG_IS_ARRAY) {
            if (!$this->argumentIsAcceptValue($mode)) {
                throw new \InvalidArgumentException('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.');
            }

            $this->hasAnArrayArgument = true;

            if (null === $default) {
                $default = [];
            } elseif (!is_array($default)) {
                throw new \LogicException('A default value for an array argument must be an array.');
            }
        }

        if ($required) {
            if (null !== $default) {
                throw new \LogicException('Cannot set a default value except for OPTIONAL-ARGUMENT mode.');
            }

            ++$this->requiredCount;
        } else {
            $this->hasOptional = true;
        }

        $this->arguments[$name] = [
            'mode' => $mode,
            'required' => $required,
            'isArray' => $isArray,
            'description' => $description,
            'default' => $default,
        ];

        return $this;
    }

    /**
     * @param int|string $name
     * @return array
     */
    public function getArgument($name)
    {
        if (!$this->hasArgument($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        $arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;

        return $arguments[$name];
    }

    /**
     * @param string|int $name The argument name or position
     * @return bool true if the InputArgument object exists, false otherwise
     */
    public function hasArgument($name)
    {
        $arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;

        return isset($arguments[$name]);
    }

    /**
     * @return array[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Returns the number of arguments.
     * @return int
     */
    public function getArgumentCount()
    {
        return count($this->arguments);
    }

    /**
     * @return int
     */
    public function getArgumentRequiredCount()
    {
        return $this->requiredCount;
    }

    /**
     * Sets the options
     *
     * @param array[] $options An array of InputOption objects
     */
    public function setOptions(array $options = [])
    {
        $this->options = $this->shortcuts = [];
        $this->addOptions($options);
    }

    /**
     * Adds an array of option
     *
     * @param array
     */
    public function addOptions(array $options = [])
    {
        $def = [
            'mode' => null,
            'description' => '',
            'default' => null,
        ];

        foreach ($options as $name => $opt) {
            $opt = array_merge($def, $opt);
            $this->addOption($name, $opt['mode'], $opt['description'], $opt['default']);
        }
    }

    /**
     * Adds an option.
     *
     * @param string|bool $name The option name, must is a string
     * @param string|array $shortcut The shortcut (can be null)
     * @param int $mode The option mode: One of the Input::OPT_* constants
     * @param string $description A description text
     * @param mixed $default The default value (must be null for InputOption::OPT_BOOL)
     *
     * @return $this
     */
    public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        if (0 === strpos($name, '--')) {
            $name = substr($name, 2);
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('An option name cannot be empty.');
        }

        if (empty($shortcut)) {
            $shortcut = null;
        }

        if (null === $mode) {
            $mode = Input::OPT_BOOLEAN;
        } elseif (!is_int($mode) || $mode > 15 || $mode < 1) {
            throw new \InvalidArgumentException(sprintf('Option mode "%s" is not valid.', $mode));
        }

        if (($isArray = $mode === Input::OPT_IS_ARRAY) && !$this->optionIsAcceptValue($mode)) {
            throw new \InvalidArgumentException('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.');
        }

        if (isset($this->options[$name])) {
            throw new \LogicException(sprintf('An option named "%s" already exists.', $name));
        }

        // set default value
        if (Input::OPT_BOOLEAN === (Input::OPT_BOOLEAN & $mode) && null !== $default) {
            throw new \LogicException('Cannot set a default value when using Input::OPT_BOOLEAN mode.');
        }

        if ($isArray) {
            if (null === $default) {
                $default = array();
            } elseif (!is_array($default)) {
                throw new \LogicException('A default value for an array option must be an array.');
            }
        }

        $default = $this->optionIsAcceptValue($mode) ? $default : false;

        if ($shortcut) {
            if (is_array($shortcut)) {
                $shortcut = implode('|', $shortcut);
            }

            $shortcuts = preg_split('{(\|)-?}', ltrim($shortcut, '-'));
            $shortcuts = array_filter($shortcuts);
            $shortcut = implode('|', $shortcuts);

            foreach ($shortcuts as $srt) {
                if (isset($this->shortcuts[$srt])) {
                    throw new \LogicException(sprintf('An option with shortcut "%s" already exists.', $srt));
                }

                $this->shortcuts[$srt] = $name;
            }
        }

        $this->options[$name] = [
            'mode' => $mode,
            'shortcut' => $shortcut, // 允许数组
            'required' => $mode === Input::OPT_REQUIRED,
            'optional' => $mode === Input::OPT_OPTIONAL,
            'description' => $description,
            'default' => $default,
        ];

        return $this;
    }

    /**
     * @param string $name
     * @return array
     */
    public function getOption($name)
    {
        if (!$this->hasOption($name)) {
            throw new \InvalidArgumentException(sprintf('The "--%s" option does not exist.', $name));
        }

        return $this->options[$name];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * Gets the array of options
     *
     * @return array[]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $name The InputOption shortcut
     * @return bool
     */
    public function hasShortcut($name)
    {
        return isset($this->shortcuts[$name]);
    }

    /**
     * Gets an option info array
     * @param string $shortcut the Shortcut name
     * @return array
     */
    public function getOptionByShortcut($shortcut)
    {
        return $this->getOption($this->shortcutToName($shortcut));
    }

    /**
     * @param string $shortcut
     * @return mixed
     */
    private function shortcutToName($shortcut)
    {
        if (!isset($this->shortcuts[$shortcut])) {
            throw new \InvalidArgumentException(sprintf('The "-%s" option does not exist.', $shortcut));
        }

        return $this->shortcuts[$shortcut];
    }

    /**
     * Gets the synopsis.
     * @param bool $short 简化版显示
     * @return string
     */
    public function getSynopsis($short = false)
    {
        $elements = $args = $opts = [];

        if ($short && $this->options) {
            $elements[] = '[options]';
        } elseif (!$short) {
            foreach ($this->options as $name => $option) {
                $value = '';

                if ($this->optionIsAcceptValue($option['mode'])) {
                    $value = sprintf(
                        ' %s%s%s',
                        $option['optional'] ? '[' : '',
                        strtoupper($name),
                        $option['optional'] ? ']' : ''
                    );
                }

                $shortcut = $option['shortcut'] ? sprintf('-%s|', $option['shortcut']) : '';
                $elements[] = sprintf('[%s--%s%s]', $shortcut, $name, $value);

                $key = "{$shortcut}--{$name}";
                $opts[$key] = ($option['required'] ? '<red>*</red>' : '') .$option['description'];
            }
        }

        if ($this->arguments && count($elements)) {
            $elements[] = '[--]';
        }

        foreach ($this->arguments as $name => $argument) {
            $des = $argument['required'] ? '<red>*</red>' . $argument['description'] : $argument['description'];

            $element = '<' . $name . '>';
            if (!$argument['required']) {
                $element = '[' . $element . ']';
            } elseif ($argument['isArray']) {
                $element = $element . ' (' . $element . ')';
            }

            if ($argument['isArray']) {
                $element .= '...';
            }

            $elements[] = $element;
            $args[$name] = $des;
        }

        $opts['-h|--help'] = 'Show help information for the command';

        return [
            'description' => $this->description,
            'usage' => implode(' ', $elements),
            'arguments' => $args,
            'options' => $opts,
            'example' => $this->example,
        ];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function argumentIsRequired($name)
    {
        if (isset($this->arguments[$name])) {
            return $this->arguments[$name]['mode'] === Input::ARG_REQUIRED;
        }

        return false;
    }

    /**
     * @param int $mode
     * @return bool
     */
    protected function argumentIsAcceptValue($mode)
    {
        return $mode === Input::ARG_REQUIRED || $mode === Input::ARG_OPTIONAL;
    }

    /**
     * @param int $mode
     * @return bool
     */
    protected function optionIsAcceptValue($mode)
    {
        return $mode === Input::OPT_REQUIRED || $mode === Input::OPT_OPTIONAL;
    }

    /**
     * @return string|array
     */
    public function getExample()
    {
        return $this->example;
    }

    /**
     * @param string|array $example
     * @return $this
     */
    public function setExample($example)
    {
        $this->example = $example;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return array
     */
    public function getShortcuts(): array
    {
        return $this->shortcuts;
    }
}
