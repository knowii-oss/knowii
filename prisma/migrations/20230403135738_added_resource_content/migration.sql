/*
  Warnings:

  - You are about to drop the column `link` on the `resources` table. All the data in the column will be lost.

*/
-- AlterTable
ALTER TABLE "public"."resources" DROP COLUMN "link",
ADD COLUMN     "content" TEXT NOT NULL DEFAULT '',
ADD COLUMN     "source" TEXT NOT NULL DEFAULT '';
