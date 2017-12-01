<?php
function test(...$args)
{
	print_r($args);
}
test();
test(1,2,3);