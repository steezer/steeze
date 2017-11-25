<?php

class TestA{
	
}

class TestB extends TestA{
	
}

class TestC{
	
}

$testB=new TestB();
var_dump(
	$testB instanceof TestC
);
