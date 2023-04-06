-- CreateTable
CREATE TABLE "public"."_resource_tags" (
    "A" UUID NOT NULL,
    "B" UUID NOT NULL
);

-- CreateIndex
CREATE UNIQUE INDEX "_resource_tags_AB_unique" ON "public"."_resource_tags"("A", "B");

-- CreateIndex
CREATE INDEX "_resource_tags_B_index" ON "public"."_resource_tags"("B");

-- AddForeignKey
ALTER TABLE "public"."_resource_tags" ADD CONSTRAINT "_resource_tags_A_fkey" FOREIGN KEY ("A") REFERENCES "public"."resources"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."_resource_tags" ADD CONSTRAINT "_resource_tags_B_fkey" FOREIGN KEY ("B") REFERENCES "public"."tags"("id") ON DELETE CASCADE ON UPDATE CASCADE;
