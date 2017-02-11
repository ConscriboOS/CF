Conscribo Open Source / Conscribo Framework

The Conscribo framework provides at this time, an Object Relation Model called DataStruct.

Datastruct provides a way to map objects to sql tables and provide an easy way to load and store the objects.

It is used by, developed for and maintained by Conscribo, a commercial accounting and memberadministration SAAS application used by approx. 700 organizations.

Datastruct has the following characteristics:

	- Pure PHP
		To ensure compatibility in a variety of environments and maximize IDE compatibility, we do not use non php constructs (like phpdoc tags), and no modules that need to be installed.
  
	- Non-conflicting
		An object using Datastruct has it's own inheritance and does not need to extend some orm abstraction. Datastruct is fully trait based, and applied where needed.

	- Well defined
		For various reasons, we like to define our own sql tables, and tell the orm how to use it. Therefore, datastruct demands you define a typed classmember to sql table mapping. 

	- Performance
		It is not the fastest orm, but thanks to lots of optimizations, can be tuned to perform well in demanding environments. 
		
Datastruct provides the at least the following 'features':

	- Create, read, update (but not delete) objects with members (fields) and store them in tables in your database without any hassle. (ORM)
	- Define fields of a variaty of basic types, or create your own types if nessecary
	- Multiple key handling: A class with a composite primary or foreign key is no problem.
	- Multiple tables in 1 class (onetoone extensionjoin). use more than one table as one table in one object
	- Associative Array join in 1 class (onetomany extensionjoin). Use records from another table as an array in an object
	- Join Objects (onetomany objectjoin). Define relations with other "Datastruct ojects" as members in your object
	- Collect objects. Define Collections (or don't and use a virtualCollection) to search, filter, sort, limit, create, read and update multiple objects. 
	- Extend your objects. Specialize your objects with extensions and their own "extended" field definitions

Version info:
0.0.1 Initial import. 
	Development on CF started in 2014 as a closed source solution. In 2017 it became open source, and it still has some "quirks" that make it rely on other Conscribo architecture.
	They wil dissapear before a stable version.

 
Roadmap:

1.0.0 Open source.
	To make it suitable to be used in public, some things have to be done:
		- Rewire the code to run outside Conscribo.
		- Make it zero configuration as much as possible. 
		- Translate code comments to english.
		- Write documentation.
		- Write unit tests (there are some acceptancetests available, but they test implementations of CF in Conscribo) 
		- Write some usage examples

