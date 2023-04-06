/*
  Warnings:

  - Added the required column `community_id` to the `tags` table without a default value. This is not possible if the table is not empty.

*/
-- AlterTable
ALTER TABLE "public"."tags" ADD COLUMN     "community_id" UUID NOT NULL;

-- AddForeignKey
ALTER TABLE "public"."tags" ADD CONSTRAINT "tags_community_id_fkey" FOREIGN KEY ("community_id") REFERENCES "public"."communities"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
