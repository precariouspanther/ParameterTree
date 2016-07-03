<?php
namespace Arcanum\ParameterTree\Exception;

/**
 * Exception denoting that a write call attempted to override a value that already exists on the ParameterTree without
 * the developer specifying the "force" option. This usually indicates accidentally overriding a previously set value,
 * or overriding entire subbranches with a single scalar value.
 * @author Adam Benson <adam@precariouspanther.net>
 */
class ValueExistsException extends \Exception
{

}