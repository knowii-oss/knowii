import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { Section } from './section';

const Story: ComponentMeta<typeof Section> = {
  component: Section,
  title: 'Section',
};
export default Story;

const Template: ComponentStory<typeof Section> = (args) => <Section {...args} />;

export const Primary = Template.bind({});
Primary.args = {
  title: 'Section title',
};
