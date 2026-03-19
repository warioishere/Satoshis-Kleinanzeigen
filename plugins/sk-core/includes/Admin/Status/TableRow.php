<?php

namespace SK\Core\Admin\Status;

use SK\Core\Abstracts\StatusElement;

class TableRow extends StatusElement {

    /**
     * @var string
     */
    protected string $type = 'table-row';
    protected bool $support_children = true;


    /**
     * @inheritDoc
     */
    public function escape_data( string $data ): string {
        // No escaping needed for table data.
        return $data;
    }
}
