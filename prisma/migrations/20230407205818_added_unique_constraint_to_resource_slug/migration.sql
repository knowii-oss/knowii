/*
  Warnings:

  - A unique constraint covering the columns `[slug]` on the table `resources` will be added. If there are existing duplicate values, this will fail.

*/
-- CreateIndex
CREATE UNIQUE INDEX "resources_slug_key" ON "public"."resources"("slug");
