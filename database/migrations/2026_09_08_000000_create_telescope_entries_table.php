<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTelescopeEntriesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('telescope_entries', function(Blueprint $table)
		{
			$table->bigIncrements('sequence');
			$table->char('uuid', 36);
			$table->char('batch_id', 36);
			$table->char('family_hash', 32)->nullable();
			$table->boolean('should_display_on_index')->default(true);
			$table->string('type', 20);
			$table->longText('content');
			$table->dateTime('created_at')->nullable();

			$table->unique('uuid');
			$table->index('batch_id');
			$table->index('family_hash');
			$table->index(['type', 'should_display_on_index']);
			$table->index('created_at');
		});

		Schema::create('telescope_entries_tags', function(Blueprint $table)
		{
			$table->char('entry_uuid', 36);
			$table->string('tag', 100);

			$table->index(['entry_uuid', 'tag']);
			$table->index('tag');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('telescope_entries_tags');
		Schema::dropIfExists('telescope_entries');
	}

}
