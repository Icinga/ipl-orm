<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;
use ipl\Stdlib\Filter;

class Book extends Model
{
    public function getTableName()
    {
        return 'book';
    }

    public function getKeyName()
    {
        return 'book_no';
    }

    public function getColumns()
    {
        return [
            'book_no',
            'title'
        ];
    }

    public function createRelations(Relations $relations)
    {
        // Many-to-many through a plain junction table (no model) with all keys declared explicitly.
        // None of the columns follow the ORM's naming conventions, so there are no defaults to fall
        // back to and the keys must survive reversal exactly as declared.
        $relations->belongsToMany('author', Author::class)
            ->through('authorship')
            ->setJoinType('LEFT')
            ->setReverseName('book')
            ->setFilter(Filter::equal('author.name', 'test'))
            ->setThroughFilter(Filter::equal('book.title', 'test'))
            ->setCandidateKey('book_no')          // book column
            ->setForeignKey('authored_book')      // junction column referencing the book
            ->setTargetForeignKey('authoring')    // junction column referencing the author
            ->setTargetCandidateKey('author_ref'); // author column
    }
}
