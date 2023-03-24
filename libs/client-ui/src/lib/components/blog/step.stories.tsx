import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { Step } from './step';

const Story: ComponentMeta<typeof Step> = {
  component: Step,
  title: 'Step',
};
export default Story;

const Template: ComponentStory<typeof Step> = (args) => <Step {...args} />;

export const Primary = Template.bind({});
Primary.args = {
  title: 'First step',
  number: 1,
};
