public function up()
{
    Schema::create('faqs', function (Blueprint $table) {
        $table->id();
        $table->string('question');
        $table->text('answer');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}
