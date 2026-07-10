<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Add_post_pre_title_and_image_alt extends Migration
{
    public function up()
    {
        $fields = [];
        if (!$this->db->fieldExists('pre_title', 'posts')) {
            $fields['pre_title'] = [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'after'      => 'title',
            ];
        }
        if (!$this->db->fieldExists('image_alt', 'posts')) {
            $fields['image_alt'] = [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ];
        }
        if (!empty($fields)) {
            $this->forge->addColumn('posts', $fields);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('pre_title', 'posts')) {
            $this->forge->dropColumn('posts', 'pre_title');
        }
        if ($this->db->fieldExists('image_alt', 'posts')) {
            $this->forge->dropColumn('posts', 'image_alt');
        }
    }
}
